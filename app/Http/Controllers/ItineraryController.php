<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreItineraryRequest;
use App\Models\Category;
use App\Models\Itinerary;
use App\Models\Place;
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

    private const DEFAULT_RADIUS_KM = 4;

    public function __construct(
        private readonly ItineraryGenerator $generator,
        private readonly UserPreferenceService $preferences,
        private readonly WeatherService $weather,
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
        $radiusKm = (int) ($data['radius_km'] ?? self::DEFAULT_RADIUS_KM);
        if ($mode === 'bike') {
            $radiusKm = max($radiusKm, 8);
        }

        $default = config('camino.default_start');
        $start = [
            'lat' => isset($data['start_lat']) ? (float) $data['start_lat'] : (float) $default['lat'],
            'lng' => isset($data['start_lng']) ? (float) $data['start_lng'] : (float) $default['lng'],
            'label' => $data['start_label'] ?? (isset($data['start_lat']) ? 'Ma position' : $default['label']),
        ];

        $startsAt = Carbon::now(config('app.timezone'));
        if (! empty($data['starts_at'])) {
            [$h, $m] = explode(':', $data['starts_at']);
            $candidate = $startsAt->copy()->setTime((int) $h, (int) $m);
            $startsAt = $candidate->lt($startsAt->copy()->subMinutes(30)) ? $candidate->addDay() : $candidate;
        }

        [$candidates, $fromSession] = $this->candidates($interests, $freeOnly, $start, $radiusKm);

        $result = $this->generator->generate($candidates, [
            'time_budget_min' => $duration,
            'budget_eur' => $budget,
            'free_only' => $freeOnly,
            'mode' => $mode,
            'start' => $start,
            'starts_at' => $startsAt,
            'interests' => $fromSession ? [] : $interests,
            'tags' => $tags,
            'profile' => $this->preferences->profile(Auth::user())['weights'],
            'preserve_order' => $fromSession,
            'use_weather' => array_key_exists('use_weather', $data) ? (bool) $data['use_weather'] : true,
        ]);

        $result['params'] = [
            'duration_minutes' => $duration,
            'budget_eur' => $budget,
            'free_only' => $freeOnly,
            'mode' => $mode,
            'interests' => $interests,
            'radius_km' => $radiusKm,
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

    public function destroy(Itinerary $itinerary)
    {
        abort_unless($itinerary->user_id === Auth::id(), 403);

        $itinerary->delete();

        return redirect()->route('itineraries.index')->with('status', 'Parcours supprimé.');
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
            // Les intérêts guident le scoring ; on garde une part de "hors intérêts" pour la diversité.
            $query->whereHas('category', fn ($q) => $q->whereIn('slug', array_merge($interests, ['restauration'])));
        } else {
            $query->whereHas('category', fn ($q) => $q->where('slug', '!=', 'restauration'));
        }
        if ($freeOnly) {
            $query->where('is_free', true);
        }

        $candidates = $query->limit(600)->get()
            ->sortBy(fn (Place $p) => (($p->lat - $start['lat']) ** 2 + (($p->lng - $start['lng']) * 0.66) ** 2))
            ->take(80)
            ->values();

        return [$candidates, false];
    }
}
