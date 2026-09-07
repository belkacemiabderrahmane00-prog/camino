<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Place;
use App\Services\AiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Trois usages de l'IA, tous avec repli sans IA :
 * - recherche par envie sur la carte (une phrase → filtres),
 * - compagnon de balade (questions pendant le parcours, avec les vrais lieux autour),
 * - audioguide génératif (récit d'un lieu, mis en cache 30 jours par langue).
 */
class AiController extends Controller
{
    private const NARRATION_VERSION = 2;

    public function status(AiService $ai)
    {
        $payload = ['enabled' => $ai->enabled(), 'providers' => $ai->status()];
        if (request()->boolean('probe')) {
            $payload['probe'] = $ai->probe();
        }

        return response()->json($payload);
    }

    /** Recherche par envie : « un musée gratuit ouvert maintenant près de moi » → filtres compris par /api/v1/pois. */
    public function intent(Request $request, AiService $ai)
    {
        $q = trim(mb_substr((string) $request->query('q', ''), 0, 200));
        if ($q === '' || ! $ai->enabled()) {
            return response()->json(['ok' => false]);
        }
        $locale = app()->getLocale();
        $result = Cache::remember('ai_intent_' . md5($locale . '|' . mb_strtolower($q)), now()->addDay(), function () use ($q, $ai, $locale) {
            $categories = Category::query()->orderBy('name')->get(['name', 'slug'])->map(fn ($c) => $c->slug . ' = ' . $c->name)->implode(', ');
            $system = "Tu es le moteur de recherche de CAMINO, un GPS culturel pour l'Île-de-France. Transforme la demande de l'utilisateur en filtres JSON.\n"
                . "Catégories disponibles (slug = nom) : {$categories}.\n"
                . "Réponds UNIQUEMENT avec un objet JSON : {\"category_slugs\": [slugs pertinents, vide si aucun], \"free\": bool (gratuit demandé), \"open_now\": bool (ouvert maintenant demandé), \"near\": bool (près de moi / autour de moi), \"events\": bool (événements, expos temporaires, concerts), \"terms\": \"mots à chercher dans les noms de lieux ou quartiers (vide si aucun)\", \"answer\": \"reformulation courte de ce que tu as compris, en " . AiService::languageName($locale) . ", 10 mots max\"}.\n"
                . "Ne mets dans terms que des noms propres ou quartiers (Marais, Montmartre, Louvre), jamais des mots génériques comme musée, gratuit, ouvert, enfants.";
            $data = $ai->json($system, $q, ['max_tokens' => 250]);
            if (! is_array($data)) {
                return null;
            }
            $slugs = array_values(array_filter(array_map(fn ($s) => is_string($s) ? trim($s) : '', (array) ($data['category_slugs'] ?? [])), fn ($s) => $s !== ''));
            $known = Category::query()->whereIn('slug', $slugs)->pluck('slug')->all();

            return [
                'category_slugs' => array_values(array_intersect($slugs, $known)),
                'free' => (bool) ($data['free'] ?? false),
                'open_now' => (bool) ($data['open_now'] ?? false),
                'near' => (bool) ($data['near'] ?? false),
                'events' => (bool) ($data['events'] ?? false),
                'terms' => trim(mb_substr((string) ($data['terms'] ?? ''), 0, 80)),
                'answer' => trim(mb_substr((string) ($data['answer'] ?? ''), 0, 120)),
            ];
        });
        if ($result === null) {
            Cache::forget('ai_intent_' . md5($locale . '|' . mb_strtolower($q)));

            return response()->json(['ok' => false]);
        }

        return response()->json(['ok' => true] + $result);
    }

    /**
     * Assistant CAMINO : une seule conversation, partout dans l'app, qui répond court et agit
     * (filtres de carte, lieux mis en avant, ajout au parcours, retrait d'une étape).
     * Les lieux recommandés viennent uniquement des candidats CAMINO autour du contexte.
     */
    public function assistant(Request $request, AiService $ai)
    {
        $data = $request->validate([
            'messages' => ['required', 'array', 'min:1', 'max:12'],
            'messages.*.role' => ['required', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string', 'max:1000'],
            'context' => ['nullable', 'array'],
            'context.page' => ['nullable', 'in:map,result,guidance,place,other'],
            'context.title' => ['nullable', 'string', 'max:200'],
            'context.steps' => ['nullable', 'array', 'max:20'],
            'context.current' => ['nullable', 'integer'],
            'context.lat' => ['nullable', 'numeric'],
            'context.lng' => ['nullable', 'numeric'],
            'context.radius' => ['nullable', 'numeric'],
            'context.place_id' => ['nullable', 'integer'],
            'context.time' => ['nullable', 'string', 'max:40'],
            'context.weather' => ['nullable', 'string', 'max:80'],
            'context.cart' => ['nullable', 'array', 'max:30'],
        ]);
        if (! $ai->enabled()) {
            return response()->json(['ok' => false, 'say' => __('Je ne suis pas disponible pour le moment.')], 503);
        }
        $ctx = $data['context'] ?? [];
        $page = $ctx['page'] ?? 'other';
        $locale = app()->getLocale();
        $lat = isset($ctx['lat']) ? (float) $ctx['lat'] : null;
        $lng = isset($ctx['lng']) ? (float) $ctx['lng'] : null;
        $radius = min(6000, max(400, (float) ($ctx['radius'] ?? 1500)));

        // Candidats : les lieux CAMINO autour du contexte (ou le lieu consulté), avec ce qu'il faut pour choisir.
        $candidates = collect();
        if ($lat !== null && $lng !== null) {
            $dlat = $radius / 111320; $dlng = $radius / (111320 * max(0.2, cos(deg2rad($lat))));
            $candidates = Place::query()->approved()->with('category')
                ->whereBetween('lat', [$lat - $dlat, $lat + $dlat])->whereBetween('lng', [$lng - $dlng, $lng + $dlng])
                ->get(['id', 'title', 'lat', 'lng', 'category_id', 'is_free', 'address', 'opening_hours', 'price_level', 'description', 'cover_image_url', 'tags'])
                ->sortBy(fn (Place $p) => ($p->lat - $lat) ** 2 + (($p->lng - $lng) * cos(deg2rad($lat))) ** 2)
                ->take(30)->values();
        }
        if (! empty($ctx['place_id'])) {
            $place = Place::query()->approved()->with('category')->find((int) $ctx['place_id']);
            if ($place) {
                $candidates = collect([$place])->merge($candidates->reject(fn ($p) => $p->id === $place->id))->take(30);
            }
        }
        $lines = '';
        foreach ($candidates as $p) {
            $window = $p->hoursFor(now());
            $d = $lat !== null ? (int) round(sqrt((($p->lat - $lat) * 111320) ** 2 + (($p->lng - $lng) * 111320 * cos(deg2rad($lat))) ** 2)) : null;
            $lines .= '#' . $p->id . ' ' . $p->title . ' | ' . ($p->category?->name ?? __('Lieu')) . ($d !== null ? ' | ' . $d . ' m' : '') . ($p->is_free ? ' | ' . __('gratuit') : '')
                . (($window['status'] ?? '') === 'open' ? ' | ' . __('ouvert') . ' ' . ($window['opens'] ?? '') . '–' . ($window['closes'] ?? '') : (($window['status'] ?? '') === 'closed' ? ' | ' . __('fermé aujourd\'hui') : ''))
                . ($p->tags ? ' | ' . implode(', ', array_slice((array) $p->tags, 0, 4)) : '')
                . ($p->description ? ' | ' . mb_substr(preg_replace('/\s+/u', ' ', strip_tags((string) $p->description)), 0, 90) : '') . "\n";
        }
        $stepsText = '';
        foreach (array_values((array) ($ctx['steps'] ?? [])) as $i => $s) {
            if (is_array($s)) {
                $stepsText .= ($i + 1) . '. ' . mb_substr((string) ($s['title'] ?? ''), 0, 80) . (isset($s['arrive']) ? ' (' . $s['arrive'] . ')' : '') . ((int) ($ctx['current'] ?? -1) === $i ? ' ← ' . __('étape en cours') : '') . "\n";
            }
        }
        $categories = Category::query()->orderBy('name')->get(['name', 'slug'])->map(fn ($c) => $c->slug . ' = ' . $c->name)->implode(', ');
        $pageRules = match ($page) {
            'map' => "L'utilisateur regarde la carte. Quand il cherche quelque chose, remplis \"filter\" (la carte l'appliquera) ET choisis jusqu'à 4 candidats dans \"places\". Si aucun candidat ne convient, laisse \"places\" vide et dis-le en une phrase.",
            'result' => "L'utilisateur regarde son parcours généré (étapes ci-dessous). Il peut demander d'ajouter un lieu (action add_place avec un id de candidat) ou de retirer une étape (action remove_step avec l'index à partir de 0). Explique en une phrase ce que tu fais.",
            'guidance' => "L'utilisateur est en train de suivre son parcours à pied (étape en cours ci-dessous). Réponds comme un compagnon de route : court, concret, rassurant. Recommande uniquement des candidats.",
            'place' => "L'utilisateur consulte la fiche du lieu « " . mb_substr((string) ($ctx['title'] ?? ''), 0, 120) . " » (premier candidat). Toute question sans objet explicite (« en trois points ? », « c'est bien pour des enfants ? ») porte sur CE lieu : réponds directement, sans demander de précision. Pour « à côté », propose d'autres candidats.",
            default => "Réponds sur CAMINO (GPS culturel d'Île-de-France : carte, parcours générés, balades à plusieurs, audioguide) et propose des candidats s'il y en a.",
        };
        $system = "Tu es CAMINO, l'assistant d'un GPS culturel pour l'Île-de-France. Tu réponds en " . AiService::languageName($locale) . ".\n"
            . "Réponds UNIQUEMENT avec un objet JSON : {\"say\": \"1 ou 2 phrases courtes (40 mots max), sans liste ni emoji\", \"places\": [{\"id\": id du candidat, \"reason\": \"pourquoi, 8 mots max\"}], \"filter\": null ou {\"category_slugs\": [slugs], \"free\": bool, \"open_now\": bool, \"near\": bool, \"events\": bool, \"terms\": \"nom propre ou quartier, sinon vide\"}, \"actions\": [] ou [{\"type\": \"add_place\", \"id\": id} | {\"type\": \"remove_step\", \"index\": n}], \"suggestions\": [\"3 questions de suite courtes (5 mots max) que l'utilisateur pourrait poser ensuite\"]}.\n"
            . "Règles : ne recommande jamais un lieu absent des candidats ; ne cite pas d'adresse ou d'horaire que tu n'as pas ; si tu ne sais pas, dis-le. Catégories pour filter : {$categories}.\n"
            . $pageRules . "\n"
            . (($ctx['title'] ?? '') !== '' ? 'Parcours « ' . mb_substr((string) $ctx['title'], 0, 120) . " » :\n" . $stepsText : '')
            . (isset($ctx['time']) ? 'Heure : ' . $ctx['time'] . "\n" : '') . (isset($ctx['weather']) ? 'Météo : ' . $ctx['weather'] . "\n" : '')
            . (! empty($ctx['cart']) ? 'Déjà dans la sélection de l\'utilisateur : ids ' . implode(', ', array_map('intval', (array) $ctx['cart'])) . "\n" : '')
            . ($lines !== '' ? "Candidats (id | nom | catégorie | distance | infos) :\n" . $lines : "Aucun candidat autour : ne recommande pas de lieu précis.\n");
        $messages = array_map(fn ($m) => ['role' => $m['role'], 'content' => $m['content']], $data['messages']);
        $out = $ai->json($system, implode("\n", array_map(fn ($m) => ($m['role'] === 'user' ? 'Utilisateur : ' : 'CAMINO : ') . $m['content'], $messages)), ['max_tokens' => 600, 'temperature' => 0.5]);
        if (! is_array($out) || trim((string) ($out['say'] ?? '')) === '') {
            return response()->json(['ok' => false, 'say' => __('Je ne suis pas disponible pour le moment.')], 503);
        }
        $byId = $candidates->keyBy('id');
        $places = [];
        foreach (array_slice((array) ($out['places'] ?? []), 0, 4) as $pick) {
            $p = $byId->get((int) ($pick['id'] ?? 0));
            if (! $p) {
                continue;
            }
            $window = $p->hoursFor(now());
            $places[] = [
                'id' => $p->id, 'title' => $p->title, 'category' => $p->category?->name, 'slug' => $p->category?->slug, 'lat' => $p->lat, 'lng' => $p->lng,
                'cover' => $p->coverThumb(320), 'url' => route('places.show', $p), 'free' => (bool) $p->is_free, 'open' => ($window['status'] ?? '') === 'open',
                'distance_m' => $lat !== null ? (int) round(sqrt((($p->lat - $lat) * 111320) ** 2 + (($p->lng - $lng) * 111320 * cos(deg2rad($lat))) ** 2)) : null,
                'reason' => mb_substr(trim((string) ($pick['reason'] ?? '')), 0, 80),
            ];
        }
        $filter = null;
        if (is_array($out['filter'] ?? null)) {
            $slugs = array_values(array_filter(array_map(fn ($s) => is_string($s) ? trim($s) : '', (array) ($out['filter']['category_slugs'] ?? [])), fn ($s) => $s !== ''));
            $knownCats = $slugs !== [] ? Category::query()->whereIn('slug', $slugs)->pluck('name', 'slug')->all() : [];
            $known = array_keys($knownCats);
            $filter = [
                'category_slugs' => array_values(array_intersect($slugs, $known)),
                'labels' => array_values(array_map(fn ($s) => $knownCats[$s], array_intersect($slugs, $known))),
                'free' => (bool) ($out['filter']['free'] ?? false), 'open_now' => (bool) ($out['filter']['open_now'] ?? false),
                'near' => (bool) ($out['filter']['near'] ?? false), 'events' => (bool) ($out['filter']['events'] ?? false),
                'terms' => trim(mb_substr((string) ($out['filter']['terms'] ?? ''), 0, 80)),
            ];
            if ($filter['category_slugs'] === [] && ! $filter['free'] && ! $filter['open_now'] && ! $filter['near'] && ! $filter['events'] && $filter['terms'] === '') {
                $filter = null;
            }
        }
        $actions = [];
        foreach (array_slice((array) ($out['actions'] ?? []), 0, 4) as $a) {
            if (! is_array($a)) {
                continue;
            }
            if (($a['type'] ?? '') === 'add_place' && $byId->has((int) ($a['id'] ?? 0))) {
                $actions[] = ['type' => 'add_place', 'id' => (int) $a['id'], 'title' => $byId->get((int) $a['id'])->title];
            } elseif (($a['type'] ?? '') === 'remove_step' && isset($a['index']) && $page === 'result') {
                $actions[] = ['type' => 'remove_step', 'index' => max(0, (int) $a['index'])];
            }
        }
        $suggestions = array_values(array_filter(array_map(fn ($s) => is_string($s) ? mb_substr(trim($s), 0, 40) : '', array_slice((array) ($out['suggestions'] ?? []), 0, 3)), fn ($s) => $s !== ''));

        return response()->json(['ok' => true, 'say' => trim((string) $out['say']), 'places' => $places, 'filter' => $filter, 'actions' => $actions, 'suggestions' => $suggestions]);
    }

    /** Audioguide génératif : récit d'un lieu (≈ 1 min à voix haute) dans la langue de l'interface, en cache 30 jours. */
    public function narration(Place $place, AiService $ai)
    {
        abort_unless($place->status === 'approved', 404);
        $locale = app()->getLocale();
        $fallback = self::trimDescription((string) ($place->translatedDescription($locale) ?? $place->description));
        if (! $ai->enabled()) {
            return response()->json(['ok' => $fallback !== '', 'text' => $fallback, 'source' => 'description']);
        }
        $key = 'ai_narration_' . self::NARRATION_VERSION . '_' . $place->id . '_' . $locale;
        $text = Cache::get($key);
        if ($text === null) {
            $description = trim((string) $place->description);
            $system = "Tu es la voix de l'audioguide CAMINO. Écris le texte qui sera lu à voix haute à un visiteur qui vient d'arriver devant ce lieu. Langue : " . AiService::languageName($locale) . ". 110 à 150 mots, phrases courtes et orales, un fait marquant, une chute chaleureuse. Pas de titre, pas de liste, pas d'emoji. Règles strictes : recopie le nom du lieu exactement comme il est donné ; n'invente aucun détail matériel (plaque, blason, couleur, inscription, objet) ni aucune date ou chiffre absents de la fiche ou dont tu n'es pas certain ; si la fiche est courte, reste général et honnête plutôt que précis et faux ; ne parle d'une fermeture ou de travaux que si la fiche le dit et sans citer d'année.";
            $user = 'Lieu : ' . $place->title . "\n" . ($place->category?->name ? 'Catégorie : ' . $place->category->name . "\n" : '') . ($place->address ? 'Adresse : ' . $place->address . "\n" : '') . ($description !== '' ? "Fiche :\n" . mb_substr($description, 0, 2500) : 'Fiche : (aucune description, appuie-toi sur ce que tu sais de ce lieu, prudemment)');
            $text = $ai->chat($system, [['role' => 'user', 'content' => $user]], ['max_tokens' => 400, 'temperature' => 0.7]);
            if ($text !== null && mb_strlen($text) > 80) {
                Cache::put($key, $text, now()->addDays(30));
            } else {
                $text = null;
            }
        }
        if ($text === null) {
            return response()->json(['ok' => $fallback !== '', 'text' => $fallback, 'source' => 'description']);
        }

        return response()->json(['ok' => true, 'text' => $text, 'source' => 'ai']);
    }

    private static function trimDescription(string $text): string
    {
        $text = preg_replace('/\s+/u', ' ', trim($text));
        if (mb_strlen($text) > 700) {
            $cut = mb_substr($text, 0, 700);
            $pos = max(mb_strrpos($cut, '. ') ?: 0, mb_strrpos($cut, '! ') ?: 0, mb_strrpos($cut, '? ') ?: 0);
            $text = $pos > 200 ? mb_substr($cut, 0, $pos + 1) : $cut . '…';
        }

        return $text;
    }
}
