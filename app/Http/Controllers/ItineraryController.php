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
use Illuminate\Support\Facades\Auth;

class ItineraryController extends Controller
{
    private const SESSION_KEY = 'itinerary_place_ids';

    private const MAX_PLACES = 15;

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

        // Date et heure de départ.
        $now = Carbon::now(config('app.timezone'));
        $startsAt = ! empty($data['date']) ? Carbon::createFromFormat('Y-m-d', $data['date'], config('app.timezone'))->startOfDay() : $now->copy();
        if (! empty($data['starts_at'])) {
            [$h, $m] = explode(':', $data['starts_at']);
            $startsAt = $startsAt->copy()->setTime((int) $h, (int) $m);
            if (empty($data['date']) && $startsAt->lt($now->copy()->subMinutes(30))) {
                $startsAt->addDay();
            }
        } elseif (! empty($data['date']) && ! $startsAt->isSameDay($now)) {
            $startsAt->setTime(10, 0);
        }
        if ($startsAt->lt($now->copy()->subMinutes(5))) {
            $startsAt = $now->copy();
        }

        // Rayon de recherche : auto selon temps et mobilité, sauf choix explicite.
        $radiusKm = isset($data['radius_km']) ? (int) $data['radius_km'] : $this->autoRadius($duration, $mode);

        [$candidates, $fromSession] = $this->candidates($interests, $freeOnly, $start, $radiusKm);
        $withLunch = ! empty($data['with_lunch']);
        $restaurants = $withLunch ? $this->restaurants($start, $candidates, $radiusKm) : collect();

        $result = $this->generator->generate($candidates, [
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
            'use_weather' => array_key_exists('use_weather', $data) ? (bool) $data['use_weather'] : true,
            'with_lunch' => $withLunch,
            'restaurants' => $restaurants,
            'alerts' => $this->activeAlerts($candidates),
        ]);

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
            'use_weather' => array_key_exists('use_weather', $data) ? (bool) $data['use_weather'] : true,
            'start_source' => $data['start_label'] ?? null,
        ];

        if (Auth::check() && $result['steps'] !== []) {
            $itinerary = Itinerary::create([
                'user_id' => Auth::id(),
                'name' => $result['title'],
                'result_json' => $result,
            ]);
            $result['itinerary_id'] = $itinerary->id;
        }

        session()->put('itinerary_result', $result);

        return redirect()->route('itineraries.create')
            ->with('status', $result['steps'] === [] ? null : (Auth::check() ? 'Parcours enregistré dans « Mes parcours ».' : 'Parcours généré. Connecte-toi pour le retrouver plus tard.'));
    }

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
        session()->put('itinerary_result', $result);

        return redirect()->route('itineraries.create')->with('status', 'Parcours « ' . $itinerary->name . ' » rechargé.');
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
     * @return array{0:\Illuminate\Support\Collection,1:bool}
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

        // Priorité aux fiches renseignées (photo, description), puis proximité.
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
    private function restaurants(array $start, \Illuminate\Support\Collection $candidates, int $radiusKm): \Illuminate\Support\Collection
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
    private function activeAlerts(\Illuminate\Support\Collection $candidates): array
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
