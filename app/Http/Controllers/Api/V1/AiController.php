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

    /** Compagnon de balade : répond aux questions pendant le parcours, avec les lieux CAMINO autour comme seule source de recommandations. */
    public function companion(Request $request, AiService $ai)
    {
        $data = $request->validate([
            'messages' => ['required', 'array', 'min:1', 'max:12'],
            'messages.*.role' => ['required', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string', 'max:1000'],
            'context' => ['nullable', 'array'],
            'context.title' => ['nullable', 'string', 'max:200'],
            'context.steps' => ['nullable', 'array', 'max:20'],
            'context.current' => ['nullable', 'integer'],
            'context.lat' => ['nullable', 'numeric'],
            'context.lng' => ['nullable', 'numeric'],
            'context.time' => ['nullable', 'string', 'max:40'],
            'context.weather' => ['nullable', 'string', 'max:80'],
        ]);
        if (! $ai->enabled()) {
            return response()->json(['ok' => false, 'answer' => __('Le compagnon IA n\'est pas disponible pour le moment.')], 503);
        }
        $ctx = $data['context'] ?? [];
        $locale = app()->getLocale();
        $stepsText = '';
        foreach (array_values((array) ($ctx['steps'] ?? [])) as $i => $s) {
            if (! is_array($s)) {
                continue;
            }
            $stepsText .= ($i + 1) . '. ' . mb_substr((string) ($s['title'] ?? ''), 0, 80) . (isset($s['arrive']) ? ' (' . __('arrivée') . ' ' . $s['arrive'] . ')' : '') . (isset($s['category']) ? ' — ' . $s['category'] : '') . ((int) ($ctx['current'] ?? -1) === $i ? ' ← ' . __('étape en cours') : '') . "\n";
        }
        $lat = isset($ctx['lat']) ? (float) $ctx['lat'] : null;
        $lng = isset($ctx['lng']) ? (float) $ctx['lng'] : null;
        $nearby = '';
        if ($lat !== null && $lng !== null) {
            $dlat = 0.012; $dlng = 0.018;
            $places = Place::query()->approved()->with('category')
                ->whereBetween('lat', [$lat - $dlat, $lat + $dlat])->whereBetween('lng', [$lng - $dlng, $lng + $dlng])
                ->get(['id', 'title', 'lat', 'lng', 'category_id', 'is_free', 'address', 'opening_hours', 'price_level'])
                ->sortBy(fn (Place $p) => ($p->lat - $lat) ** 2 + (($p->lng - $lng) * cos(deg2rad($lat))) ** 2)
                ->take(10);
            foreach ($places as $p) {
                $window = $p->hoursFor(now());
                $d = (int) round(sqrt((($p->lat - $lat) * 111320) ** 2 + (($p->lng - $lng) * 111320 * cos(deg2rad($lat))) ** 2));
                $nearby .= '- ' . $p->title . ' (' . ($p->category?->name ?? __('Lieu')) . ', ' . $d . ' m' . ($p->is_free ? ', ' . __('gratuit') : '') . (($window['status'] ?? '') === 'open' ? ', ' . __('ouvert') . ' ' . ($window['opens'] ?? '') . '–' . ($window['closes'] ?? '') : (($window['status'] ?? '') === 'closed' ? ', ' . __('fermé aujourd\'hui') : '')) . ($p->address ? ', ' . mb_substr($p->address, 0, 60) : '') . ")\n";
            }
        }
        $system = "Tu es CAMINO, compagnon de balade culturelle en Île-de-France. Tu accompagnes une personne pendant son parcours. Réponds en " . AiService::languageName($locale) . ", en 2 à 4 phrases (80 mots max), utile et concret, sans emoji, sans liste à puces.\n"
            . "Parcours « " . mb_substr((string) ($ctx['title'] ?? ''), 0, 120) . " » :\n" . ($stepsText ?: "(inconnu)\n")
            . (isset($ctx['time']) ? 'Heure actuelle : ' . $ctx['time'] . "\n" : '') . (isset($ctx['weather']) ? 'Météo : ' . $ctx['weather'] . "\n" : '')
            . ($nearby !== '' ? "Lieux CAMINO autour de la position actuelle (seule source autorisée pour recommander un lieu ; cite-les par leur nom exact) :\n" . $nearby : "Aucun lieu connu autour : ne recommande pas d'adresse précise.\n")
            . "Si on te demande l'histoire d'un lieu, raconte-la avec des faits sûrs, sans inventer de dates. Si tu ne sais pas, dis-le simplement.";
        $answer = $ai->chat($system, array_map(fn ($m) => ['role' => $m['role'], 'content' => $m['content']], $data['messages']), ['max_tokens' => 350, 'temperature' => 0.6]);
        if ($answer === null) {
            return response()->json(['ok' => false, 'answer' => __('Le compagnon IA n\'est pas disponible pour le moment.')], 503);
        }

        return response()->json(['ok' => true, 'answer' => $answer]);
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
