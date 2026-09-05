<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreItineraryRequest;
use App\Models\Category;
use App\Models\Itinerary;
use App\Models\Place;
use App\Models\PlaceAlert;
use App\Services\GeocodingService;
use App\Services\ItineraryGenerator;
use App\Services\UserPreferenceService;
use App\Services\WeatherService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class ItineraryController extends Controller
{
    private const SESSION_KEY = 'itinerary_place_ids';

    private const LOCKED_KEY = 'itinerary_locked';

    private const VARIANTS_KEY = 'itinerary_variants';

    private const MAX_PLACES = 15;

    /** Variantes proposées à partir de la même liste de candidats. */
    private const VARIANTS = [
        'mix' => ['label' => 'Équilibré', 'icon' => 'balance', 'adjust' => []],
        'culture' => ['label' => 'Culture', 'icon' => 'palette', 'adjust' => ['musee' => 1.6, 'monument' => 1.2, 'lieu-culturel' => 1.0, 'parc-jardin' => -0.8, 'street-art' => -0.5]],
        'detente' => ['label' => 'Détente', 'icon' => 'park', 'adjust' => ['parc-jardin' => 1.8, 'street-art' => 1.0, 'itineraire' => 0.8, 'musee' => -0.8, 'monument' => -0.3]],
    ];

    public function __construct(
        private readonly ItineraryGenerator $generator,
        private readonly UserPreferenceService $preferences,
        private readonly WeatherService $weather,
        private readonly GeocodingService $geocoder,
    ) {}

    public function create()
    {
        $categories = Category::whereIn('slug', ['musee', 'monument', 'parc-jardin', 'lieu-culturel', 'street-art', 'evenement-culturel', 'restauration', 'itineraire'])
            ->orderByRaw("case slug when 'musee' then 1 when 'monument' then 2 when 'parc-jardin' then 3 when 'lieu-culturel' then 4 when 'evenement-culturel' then 5 when 'street-art' then 6 when 'restauration' then 7 else 8 end")
            ->get();

        $placeIds = session(self::SESSION_KEY, []);
        $itineraryPlaces = collect();
        if (! empty($placeIds)) {
            $itineraryPlaces = Place::with('category')->whereIn('id', $placeIds)->get()
                ->sortBy(fn ($p) => array_search($p->id, $placeIds))->values();
        }

        $result = session('itinerary_result');
        $start = config('camino.default_start');
        $forecast = $this->weather->forecast((float) $start['lat'], (float) $start['lng']);
        $profile = $this->preferences->profile(Auth::user());

        return view('itineraries.create', [
            'categories' => $categories,
            'itineraryPlaces' => $itineraryPlaces,
            'result' => $result,
            'forecast' => $forecast,
            'profile' => $profile,
            'defaultStart' => $start,
            'user' => Auth::user(),
        ]);
    }

    public function store(StoreItineraryRequest $request)
    {
        $data = $request->validated();

        $duration = (int) $data['duration_minutes'];
        $budget = isset($data['budget_eur']) && $data['budget_eur'] !== null ? (float) $data['budget_eur'] : null;
        $freeOnly = ! empty($data['free_only']);
        $mode = $data['mode'] ?? 'walk';
        $interests = array_values($data['interests'] ?? []);
        if ($interests === [] && Auth::check() && ! empty(Auth::user()->interests)) {
            $interests = array_values((array) Auth::user()->interests);
        }
        $tags = array_values($data['tags'] ?? []);
        $surprise = ! empty($data['surprise']);

        // Départ : adresse / position / carte, sinon le centre de Paris.
        $default = config('camino.default_start');
        $hasStart = isset($data['start_lat'], $data['start_lng']);
        $start = [
            'lat' => $hasStart ? (float) $data['start_lat'] : (float) $default['lat'],
            'lng' => $hasStart ? (float) $data['start_lng'] : (float) $default['lng'],
            'label' => $data['start_label'] ?? ($hasStart ? 'Ma position' : $default['label']),
        ];
        if ($hasStart && in_array($start['label'], ['Ma position', 'Point sur la carte'], true)) {
            $start['label'] = $this->geocoder->reverse($start['lat'], $start['lng']) ?? $start['label'];
        }

        // Arrivée : boucle, libre, ou point précis.
        $endMode = $data['end_mode'] ?? 'open';
        $end = null;
        if ($endMode === 'point' && isset($data['end_lat'], $data['end_lng'])) {
            $end = ['lat' => (float) $data['end_lat'], 'lng' => (float) $data['end_lng'], 'label' => $data['end_label'] ?? 'Arrivée'];
        }

        $startsAt = $this->startsAt($data['date'] ?? null, $data['starts_at'] ?? null);
        $radiusKm = isset($data['radius_km']) ? (int) $data['radius_km'] : $this->autoRadius($duration, $mode);

        [$candidates, $fromSession] = $this->candidates($interests, $freeOnly, $start, $radiusKm);
        $withLunch = ! empty($data['with_lunch']);
        $restaurants = $withLunch ? $this->restaurants($start, $candidates, $radiusKm) : collect();

        // Lieux verrouillés lors d'une édition précédente : ils restent dans le parcours.
        $locked = array_map('intval', session(self::LOCKED_KEY, []));
        if ($locked !== [] && ! $fromSession) {
            $missing = array_diff($locked, $candidates->pluck('id')->all());
            if ($missing !== []) {
                $candidates = $candidates->concat(Place::with('category')->withAvg('reviews', 'rating')->whereIn('id', $missing)->get());
            }
        }

        $preset = $surprise ? array_rand(self::VARIANTS) : 'mix';
        $useWeather = array_key_exists('use_weather', $data) ? (bool) $data['use_weather'] : true;
        $options = [
            'time_budget_min' => $duration,
            'budget_eur' => $budget,
            'free_only' => $freeOnly,
            'mode' => $mode,
            'start' => $start,
            'end' => $end,
            'loop' => $endMode === 'loop',
            'starts_at' => $startsAt,
            'interests' => $fromSession ? [] : $interests,
            'tags' => $tags,
            'profile' => $this->preferences->profile(Auth::user())['weights'],
            'preserve_order' => $fromSession,
            'use_weather' => $useWeather,
            'with_lunch' => $withLunch,
            'restaurants' => $restaurants,
            'alerts' => $this->activeAlerts($candidates),
            'required' => $fromSession ? [] : $locked,
            'score_adjust' => self::VARIANTS[$preset]['adjust'],
            'jitter' => $surprise ? 1.6 : 0,
        ];

        $result = $this->generator->generate($candidates, $options);
        if ($surprise && $result['steps'] !== []) {
            $result['title'] = 'Surprise · ' . $result['title'];
        }

        $result['params'] = [
            'duration_minutes' => $duration,
            'budget_eur' => $budget,
            'free_only' => $freeOnly,
            'mode' => $mode,
            'interests' => $interests,
            'radius_km' => $data['radius_km'] ?? null,
            'end_mode' => $endMode,
            'end' => $end,
            'date' => $startsAt->format('Y-m-d'),
            'starts_at' => $startsAt->format('H:i'),
            'with_lunch' => $withLunch,
            'use_weather' => $useWeather,
            'start_source' => $data['start_label'] ?? null,
        ];

        // Trois propositions à partir des mêmes candidats (la matrice de temps est déjà en cache).
        $variants = [];
        if (! $fromSession && ! $surprise && $result['steps'] !== [] && ! empty($result['shortlist_ids'])) {
            $variants['mix'] = $result;
            foreach (['culture', 'detente'] as $key) {
                $alt = $this->generator->generate($candidates, ['score_adjust' => self::VARIANTS[$key]['adjust'], 'shortlist_ids' => $result['shortlist_ids']] + $options);
                if ($alt['steps'] !== [] && $this->signature($alt) !== $this->signature($result)) {
                    $alt['params'] = $result['params'];
                    $variants[$key] = $alt;
                }
            }
            if (count($variants) < 2) {
                $variants = [];
            }
            $result['variants'] = $this->variantSummaries($variants, 'mix');
        }
        session()->put(self::VARIANTS_KEY, $variants);

        $this->persist($result);

        return redirect()->route('itineraries.create')
            ->with('status', $result['steps'] === [] ? null : (Auth::check() ? 'Parcours enregistré dans « Mes parcours ».' : 'Parcours généré. Connecte-toi pour le retrouver plus tard.'));
    }

    /** Bascule vers une des propositions calculées. */
    public function chooseVariant(string $key)
    {
        $variants = session(self::VARIANTS_KEY, []);
        $current = session('itinerary_result');
        abort_unless(isset($variants[$key]) && $current, 404);

        $chosen = $variants[$key];
        $chosen['variants'] = $this->variantSummaries($variants, $key);
        $chosen['itinerary_id'] = $current['itinerary_id'] ?? null;
        $this->persist($chosen);

        return redirect()->route('itineraries.create')->with('status', 'Proposition « ' . self::VARIANTS[$key]['label'] . ' » sélectionnée.');
    }

    // ------------------------------------------------------------------ édition d'un parcours généré

    public function editRemove(int $index)
    {
        $result = $this->current($index);
        $steps = array_values($result['steps']);
        if (count($steps) <= 1) {
            return back()->with('status', 'Un parcours doit garder au moins une étape.');
        }
        $removed = $steps[$index];
        array_splice($steps, $index, 1);
        $this->forgetLock((int) $removed['place_id']);
        $this->rebuild($result, $steps);

        return back()->with('status', '« ' . $removed['title'] . ' » retiré, horaires recalculés.');
    }

    public function editMove(Request $request, int $index)
    {
        $result = $this->current($index);
        $steps = array_values($result['steps']);
        $to = $request->input('direction') === 'up' ? $index - 1 : $index + 1;
        if (! isset($steps[$to])) {
            return back();
        }
        [$steps[$index], $steps[$to]] = [$steps[$to], $steps[$index]];
        $this->rebuild($result, $steps);

        return back()->with('status', 'Ordre modifié, trajets recalculés.');
    }

    public function editDuration(Request $request, int $index)
    {
        $result = $this->current($index);
        $steps = array_values($result['steps']);
        $delta = (int) $request->input('delta', 15);
        $steps[$index]['visit_minutes'] = max(15, min(240, (int) $steps[$index]['visit_minutes'] + $delta));
        $this->rebuild($result, $steps);

        return back()->with('status', 'Durée ajustée : ' . $steps[$index]['visit_minutes'] . ' min sur place.');
    }

    public function editLock(int $index)
    {
        $result = $this->current($index);
        $placeId = (int) $result['steps'][$index]['place_id'];
        $locked = array_map('intval', session(self::LOCKED_KEY, []));
        $isLocked = in_array($placeId, $locked, true);
        $locked = $isLocked ? array_values(array_diff($locked, [$placeId])) : array_values(array_unique(array_merge($locked, [$placeId])));
        session()->put(self::LOCKED_KEY, $locked);
        $result['steps'][$index]['locked'] = ! $isLocked;
        $this->persist($result);

        return back()->with('status', $isLocked ? 'Étape déverrouillée.' : 'Étape verrouillée : elle restera dans le parcours si tu recalcules.');
    }

    public function editReplace(int $index)
    {
        $result = $this->current($index);
        $steps = array_values($result['steps']);
        $step = $steps[$index];
        $date = Carbon::parse($result['starts_at'])->startOfDay();
        $usedIds = array_map(fn ($s) => (int) $s['place_id'], $steps);

        $d = 1.5;
        $candidates = Place::query()->with('category')->withAvg('reviews', 'rating')->visible()
            ->whereNotIn('id', $usedIds)
            ->whereBetween('lat', [$step['lat'] - $d / 111, $step['lat'] + $d / 111])
            ->whereBetween('lng', [$step['lng'] - $d / 73, $step['lng'] + $d / 73])
            ->when(! empty($step['category_slug']), fn ($q) => $q->whereHas('category', fn ($c) => $c->where('slug', $step['category_slug'])))
            ->limit(120)->get()
            ->filter(fn (Place $p) => $p->hoursFor($date)['status'] !== 'closed')
            ->sortBy(fn (Place $p) => (($p->lat - $step['lat']) ** 2 + (($p->lng - $step['lng']) * 0.66) ** 2) * ($p->cover_image_url ? 0.5 : 1.0) * ($p->reviews_avg_rating ? 0.8 : 1.0) * ($p->description ? 0.9 : 1.0));

        $replacement = $candidates->first();
        if (! $replacement) {
            return back()->with('status', 'Aucun lieu similaire ouvert à proximité.');
        }
        $steps[$index] = ['place_id' => $replacement->id, 'visit_minutes' => $replacement->visit_duration_min ?: 60];
        $this->forgetLock((int) $step['place_id']);
        $this->rebuild($result, $steps);

        return back()->with('status', '« ' . $step['title'] . ' » remplacé par « ' . $replacement->title . ' ».');
    }

    // ------------------------------------------------------------------ sélection manuelle

    public function addPlace(Place $place)
    {
        $ids = session(self::SESSION_KEY, []);
        $ids = array_values(array_unique(array_merge($ids, [$place->id])));
        if (count($ids) > self::MAX_PLACES) {
            $ids = array_slice($ids, 0, self::MAX_PLACES);
        }
        session()->put(self::SESSION_KEY, $ids);

        return back()->with('status', 'Lieu ajouté à ton parcours.');
    }

    public function removePlace(Place $place)
    {
        $ids = array_values(array_diff(session(self::SESSION_KEY, []), [$place->id]));
        session()->put(self::SESSION_KEY, $ids);

        return back()->with('status', 'Lieu retiré du parcours.');
    }

    public function clearPlaces(Request $request)
    {
        session()->forget(self::SESSION_KEY);

        return redirect()->route('itineraries.create')->with('status', 'Sélection vidée.');
    }

    // ------------------------------------------------------------------ parcours enregistrés

    public function index()
    {
        $itineraries = Auth::user()->itineraries()->latest()->paginate(10);

        return view('itineraries.index', ['itineraries' => $itineraries]);
    }

    public function show(Itinerary $itinerary)
    {
        abort_unless($itinerary->user_id === Auth::id() || Auth::user()?->is_admin, 403);

        return view('itineraries.show', ['itinerary' => $itinerary, 'result' => $itinerary->result_json]);
    }

    public function replay(Itinerary $itinerary)
    {
        abort_unless($itinerary->user_id === Auth::id(), 403);

        $result = $itinerary->result_json;
        $result['itinerary_id'] = $itinerary->id;
        unset($result['variants']);
        session()->forget(self::VARIANTS_KEY);
        session()->put('itinerary_result', $result);

        return redirect()->route('itineraries.create')->with('status', 'Parcours « ' . $itinerary->name . ' » rechargé.');
    }

    public function share(Itinerary $itinerary)
    {
        abort_unless($itinerary->user_id === Auth::id(), 403);

        return back()->with('status', 'Lien de partage prêt.')->with('share_url', $itinerary->shareUrl());
    }

    public function gpx(Itinerary $itinerary)
    {
        abort_unless($itinerary->user_id === Auth::id() || Auth::user()?->is_admin, 403);

        return $this->gpxResponse($itinerary);
    }

    /** Page publique d'un parcours partagé. */
    public function shared(string $token)
    {
        $itinerary = Itinerary::where('share_token', $token)->firstOrFail();

        return view('itineraries.shared', ['itinerary' => $itinerary, 'result' => $itinerary->result_json, 'token' => $token]);
    }

    /** Copie un parcours partagé dans la session du visiteur pour le suivre ou le modifier. */
    public function sharedOpen(string $token)
    {
        $itinerary = Itinerary::where('share_token', $token)->firstOrFail();
        $result = $itinerary->result_json;
        unset($result['itinerary_id'], $result['variants']);
        session()->forget(self::VARIANTS_KEY);
        session()->forget(self::LOCKED_KEY);
        session()->put('itinerary_result', $result);

        return redirect()->route('itineraries.create')->with('status', 'Parcours « ' . $itinerary->name . ' » ouvert. Tu peux le suivre ou le modifier.');
    }

    public function sharedGpx(string $token)
    {
        return $this->gpxResponse(Itinerary::where('share_token', $token)->firstOrFail());
    }

    /** Guidage en direct du parcours en session. */
    public function navigate()
    {
        $result = session('itinerary_result');
        if (! $result || empty($result['steps'])) {
            return redirect()->route('itineraries.create')->with('status', 'Génère un parcours avant de lancer le guidage.');
        }

        return view('itineraries.navigate', ['result' => $result, 'backUrl' => route('itineraries.create')]);
    }

    /** Guidage d'un parcours enregistré. */
    public function navigateSaved(Itinerary $itinerary)
    {
        abort_unless($itinerary->user_id === Auth::id() || Auth::user()?->is_admin, 403);

        return view('itineraries.navigate', ['result' => $itinerary->result_json, 'backUrl' => route('itineraries.show', $itinerary)]);
    }

    public function destroy(Itinerary $itinerary)
    {
        abort_unless($itinerary->user_id === Auth::id(), 403);

        $itinerary->delete();

        return redirect()->route('itineraries.index')->with('status', 'Parcours supprimé.');
    }

    // ------------------------------------------------------------------ internes

    private function gpxResponse(Itinerary $itinerary)
    {
        $name = \Illuminate\Support\Str::slug($itinerary->name) ?: 'parcours';

        return response($itinerary->toGpx(), 200, [
            'Content-Type' => 'application/gpx+xml; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="camino-' . $name . '.gpx"',
        ]);
    }

    /** Résultat en session, avec l'étape demandée. */
    private function current(int $index): array
    {
        $result = session('itinerary_result');
        abort_unless($result && isset($result['steps'][$index]), 404);

        return $result;
    }

    private function forgetLock(int $placeId): void
    {
        session()->put(self::LOCKED_KEY, array_values(array_diff(array_map('intval', session(self::LOCKED_KEY, [])), [$placeId])));
    }

    /**
     * Recalcule un parcours à partir d'une liste ordonnée d'étapes (place_id + durée), avec les vrais trajets.
     *
     * @param array<int,array<string,mixed>> $steps
     */
    private function rebuild(array $result, array $steps): void
    {
        $ids = array_map(fn ($s) => (int) $s['place_id'], $steps);
        $places = Place::with('category')->withAvg('reviews', 'rating')->whereIn('id', $ids)->get()->keyBy('id');
        $ordered = collect($ids)->map(fn ($id) => $places->get($id))->filter()->values();
        $overrides = [];
        foreach ($steps as $s) {
            if (! empty($s['visit_minutes'])) {
                $overrides[(int) $s['place_id']] = (int) $s['visit_minutes'];
            }
        }
        $params = $result['params'] ?? [];
        $startsAt = Carbon::parse($result['starts_at'], config('app.timezone'));

        $new = $this->generator->generate($ordered, [
            'time_budget_min' => (int) ($params['duration_minutes'] ?? max(60, $result['total_minutes'] ?? 180)),
            'budget_eur' => $params['budget_eur'] ?? null,
            'mode' => $result['mode'] ?? 'walk',
            'start' => $result['start'],
            'end' => ($params['end_mode'] ?? 'open') === 'point' ? ($result['end'] ?? null) : null,
            'loop' => (bool) ($result['loop'] ?? false),
            'starts_at' => $startsAt,
            'preserve_order' => true,
            'strict_time' => false,
            'use_weather' => (bool) ($params['use_weather'] ?? true),
            'visit_overrides' => $overrides,
            'required' => array_map('intval', session(self::LOCKED_KEY, [])),
            'alerts' => $this->activeAlerts($ordered),
        ]);
        $new['params'] = $params;
        $new['edited'] = true;
        $locked = array_map('intval', session(self::LOCKED_KEY, []));
        foreach ($new['steps'] as &$st) {
            $st['locked'] = in_array((int) $st['place_id'], $locked, true);
        }
        unset($st);
        $new['variants'] = $result['variants'] ?? [];
        $new['itinerary_id'] = $result['itinerary_id'] ?? null;
        $this->persist($new);
    }

    /** Session + parcours enregistré (mis à jour ou créé pour un utilisateur connecté). */
    private function persist(array $result): void
    {
        if (Auth::check() && $result['steps'] !== []) {
            $itinerary = ! empty($result['itinerary_id']) ? Itinerary::where('user_id', Auth::id())->find($result['itinerary_id']) : null;
            $payload = $result;
            unset($payload['itinerary_id']);
            if ($itinerary) {
                $itinerary->update(['name' => $result['title'], 'result_json' => $payload]);
            } else {
                $itinerary = Itinerary::create(['user_id' => Auth::id(), 'name' => $result['title'], 'result_json' => $payload]);
            }
            $result['itinerary_id'] = $itinerary->id;
        }
        session()->put('itinerary_result', $result);
    }

    private function signature(array $result): string
    {
        return implode(',', array_map(fn ($s) => $s['place_id'], $result['steps']));
    }

    /** @return array<int,array<string,mixed>> */
    private function variantSummaries(array $variants, string $active): array
    {
        $out = [];
        foreach ($variants as $key => $r) {
            $out[] = [
                'key' => $key,
                'label' => self::VARIANTS[$key]['label'],
                'icon' => self::VARIANTS[$key]['icon'],
                'active' => $key === $active,
                'steps' => count(array_filter($r['steps'], fn ($s) => ($s['kind'] ?? 'visit') === 'visit')),
                'minutes' => $r['total_minutes'],
                'km' => $r['total_distance_km'],
                'cost' => $r['total_cost_eur'],
                'titles' => array_slice(array_map(fn ($s) => $s['title'], $r['steps']), 0, 3),
            ];
        }

        return $out;
    }

    private function startsAt(?string $date, ?string $time): Carbon
    {
        $now = Carbon::now(config('app.timezone'));
        $startsAt = $date ? Carbon::createFromFormat('Y-m-d', $date, config('app.timezone'))->startOfDay() : $now->copy();
        if ($time) {
            [$h, $m] = explode(':', $time);
            $startsAt = $startsAt->copy()->setTime((int) $h, (int) $m);
            if (! $date && $startsAt->lt($now->copy()->subMinutes(30))) {
                $startsAt->addDay();
            }
        } elseif ($date && ! $startsAt->isSameDay($now)) {
            $startsAt->setTime(10, 0);
        }
        if ($startsAt->lt($now->copy()->subMinutes(5))) {
            $startsAt = $now->copy();
        }

        return $startsAt;
    }

    /** Rayon raisonnable : à pied ~1 km par heure disponible, à vélo ~2,5 km. */
    private function autoRadius(int $minutes, string $mode): int
    {
        $hours = $minutes / 60;

        return $mode === 'bike'
            ? (int) max(5, min(20, round(3 + $hours * 2.5)))
            : (int) max(2, min(8, round(1.5 + $hours * 1.0)));
    }

    /**
     * Candidats : la sélection manuelle (session) si elle existe, sinon les lieux visibles
     * dans un carré de ±rayon autour du départ, filtrés par intérêts et gratuité.
     *
     * @return array{0:Collection,1:bool}
     */
    private function candidates(array $interests, bool $freeOnly, array $start, int $radiusKm): array
    {
        $placeIds = session(self::SESSION_KEY, []);

        if (! empty($placeIds)) {
            $places = Place::query()->with('category')->withAvg('reviews', 'rating')
                ->whereIn('id', $placeIds)->whereNotNull('lat')->whereNotNull('lng')->approved()->get()
                ->sortBy(fn (Place $p) => array_search($p->id, $placeIds))->values();

            return [$places, true];
        }

        $dLat = $radiusKm / 111;
        $dLng = $radiusKm / 73;

        $query = Place::query()->with('category')->withAvg('reviews', 'rating')
            ->visible()
            ->whereBetween('lat', [$start['lat'] - $dLat, $start['lat'] + $dLat])
            ->whereBetween('lng', [$start['lng'] - $dLng, $start['lng'] + $dLng]);

        if ($interests !== []) {
            $query->whereHas('category', fn ($q) => $q->whereIn('slug', $interests));
        } else {
            $query->whereHas('category', fn ($q) => $q->where('slug', '!=', 'restauration'));
        }
        if ($freeOnly) {
            $query->where('is_free', true);
        }

        // Doublons du flux (même lieu importé deux fois) : on garde la fiche la mieux renseignée.
        $candidates = $query->limit(700)->get()
            ->sortByDesc(fn (Place $p) => ($p->cover_image_url ? 2 : 0) + ($p->opening_hours ? 1 : 0) + ($p->description ? 1 : 0))
            ->unique(fn (Place $p) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', $p->title))))
            ->sortBy(fn (Place $p) => (($p->lat - $start['lat']) ** 2 + (($p->lng - $start['lng']) * 0.66) ** 2) * ($p->cover_image_url ? 0.6 : 1.0) * ($p->opening_hours ? 0.85 : 1.0))
            ->take(90)
            ->values();

        return [$candidates, false];
    }

    /** Restaurants proches du centre de gravité des candidats, pour la pause déjeuner. */
    private function restaurants(array $start, Collection $candidates, int $radiusKm): Collection
    {
        $lat = $candidates->isNotEmpty() ? (float) $candidates->avg('lat') : $start['lat'];
        $lng = $candidates->isNotEmpty() ? (float) $candidates->avg('lng') : $start['lng'];
        $d = max(1.5, $radiusKm * 0.6);

        return Place::query()->with('category')->visible()
            ->whereHas('category', fn ($q) => $q->where('slug', 'restauration'))
            ->whereBetween('lat', [$lat - $d / 111, $lat + $d / 111])
            ->whereBetween('lng', [$lng - $d / 73, $lng + $d / 73])
            ->limit(200)->get()
            ->sortBy(fn (Place $p) => (($p->lat - $lat) ** 2 + (($p->lng - $lng) * 0.66) ** 2) * ($p->cover_image_url ? 0.7 : 1.0))
            ->take(8)->values();
    }

    /** Alertes communautaires actives par lieu (fermeture, affluence). */
    private function activeAlerts(Collection $candidates): array
    {
        if ($candidates->isEmpty()) {
            return [];
        }
        $out = [];
        PlaceAlert::active()->whereIn('place_id', $candidates->pluck('id'))->get(['place_id', 'type'])
            ->each(function ($a) use (&$out) {
                $out[$a->place_id][] = $a->type;
            });

        return $out;
    }
}
