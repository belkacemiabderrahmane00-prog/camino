<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreItineraryRequest;
use App\Models\Category;
use App\Models\Itinerary;
use App\Models\Place;
use App\Services\ItineraryGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use stdClass;

class ItineraryController extends Controller
{
    private const SESSION_KEY = 'itinerary_place_ids';

    private const MAX_PLACES = 15;

    public function __construct(
        private ItineraryGenerator $itineraryGenerator
    ) {}

    public function create()
    {
        $categories = class_exists(Category::class) ? Category::all() : collect();
        $placeIds = session(self::SESSION_KEY, []);
        $itineraryPlaces = collect();
        if (! empty($placeIds)) {
            $itineraryPlaces = Place::whereIn('id', $placeIds)->get()->sortBy(fn ($p) => array_search($p->id, $placeIds))->values();
        }

        return view('itineraries.create', [
            'categories' => $categories,
            'itineraryPlaces' => $itineraryPlaces,
        ]);
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

        return redirect()->route('itineraries.create')->with('status', 'Parcours vidé.');
    }

    public function store(StoreItineraryRequest $request)
    {
        $data = $request->validated();

        $duration = (int) $data['duration_minutes'];
        $budget = (float) $data['budget_eur'];
        $freeOnly = ! empty($data['free_only']);
        $categoryIds = $data['category_ids'] ?? [];

        [$places, $fromSession] = $this->getPlacesForItinerary($categoryIds, $freeOnly);

        if ($places->isEmpty()) {
            $result = [
                'estimated_total_minutes' => 0,
                'estimated_total_budget' => 0,
                'steps' => [],
                'warnings' => [
                    $freeOnly ? 'Aucun lieu gratuit trouvé. Ajoute des lieux depuis la carte ou décoche « Prioriser les lieux gratuits ».' : 'Aucun lieu trouvé. Ajoute des lieux depuis la carte ou élargis les catégories.',
                ],
            ];
        } else {
            $raw = $this->itineraryGenerator->generate(
                $places,
                $duration,
                null,
                null,
                [
                    'free_only' => $freeOnly,
                    'budget_eur' => $budget > 0 ? $budget : null,
                    'preserve_order' => $fromSession,
                ]
            );

            $result = [
                'estimated_total_minutes' => $raw['totalDurationMin'],
                'estimated_total_budget' => $raw['totalBudgetEur'],
                'total_distance_km' => $raw['totalDistanceKm'],
                'steps' => array_map(function ($step) {
                    return [
                        'order' => $step['order'],
                        'place_id' => $step['place_id'],
                        'title' => $step['title'],
                        'address' => $step['address'],
                        'visit_minutes' => $step['visitDurationMin'],
                        'travel_minutes' => $step['travelDurationMin'],
                        'cost_eur' => $step['costEur'],
                        'category' => $step['category'] ?? null,
                        'lat' => $step['lat'],
                        'lng' => $step['lng'],
                    ];
                }, $raw['steps']),
                'start' => $raw['start'] ?? null,
                'warnings' => $raw['warnings'],
            ];
        }

        if (Auth::check()) {
            Itinerary::create([
                'user_id' => Auth::id(),
                'name' => 'Parcours ' . now()->format('d/m H\hi'),
                'result_json' => $result,
            ]);
        }

        $itinerary = new stdClass();
        $itinerary->result_json = $result;
        session()->put('itinerary', $itinerary);

        return redirect()->route('itineraries.create')
            ->with('status', Auth::check() ? 'Parcours enregistré !' : 'Parcours généré.');
    }

    /**
     * Historique des parcours de l'utilisateur connecté.
     */
    public function index()
    {
        $itineraries = Auth::user()->itineraries()->latest()->paginate(10);

        return view('itineraries.index', ['itineraries' => $itineraries]);
    }

    /**
     * Recharge un parcours enregistré dans la page de génération.
     */
    public function replay(Itinerary $itinerary)
    {
        abort_unless($itinerary->user_id === Auth::id(), 403);

        session()->put('itinerary', $itinerary);

        return redirect()->route('itineraries.create')->with('status', 'Parcours « ' . $itinerary->name . ' » rechargé.');
    }

    public function destroy(Itinerary $itinerary)
    {
        abort_unless($itinerary->user_id === Auth::id(), 403);

        $itinerary->delete();

        return redirect()->route('itineraries.index')->with('status', 'Parcours supprimé.');
    }

    /**
     * Lieux pour le parcours : session (ordre utilisateur) ou requête par catégories + gratuit.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: bool}
     */
    private function getPlacesForItinerary(array $categoryIds, bool $freeOnly): array
    {
        $placeIds = session(self::SESSION_KEY, []);

        if (! empty($placeIds)) {
            $places = Place::query()
                ->with('category')
                ->whereIn('id', $placeIds)
                ->whereNotNull('lat')
                ->whereNotNull('lng')
                ->approved()
                ->get()
                ->sortBy(fn (Place $p) => array_search($p->id, $placeIds))
                ->values();

            if ($freeOnly) {
                $places = $places->filter(fn (Place $p) => $p->is_free)->values();
            }

            return [$places, true];
        }

        $query = Place::query()
            ->with('category')
            ->approved()
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->latest();

        if ($categoryIds !== []) {
            $query->whereIn('category_id', $categoryIds);
        }

        if ($freeOnly) {
            $query->where('is_free', true);
        }

        return [$query->limit(50)->get(), false];
    }
}
