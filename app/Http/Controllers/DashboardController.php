<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Place;
use App\Models\PlaceAlert;
use App\Services\UserPreferenceService;
use App\Services\UserStatsService;
use App\Services\WeatherService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function __construct(
        private readonly WeatherService $weather,
        private readonly UserPreferenceService $preferences,
        private readonly UserStatsService $stats,
    ) {}

    /** Page publique d'accueil. */
    public function landing()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        $start = config('camino.default_start');
        $forecast = $this->weather->forecast((float) $start['lat'], (float) $start['lng']);
        $advice = $this->weather->advice($forecast);

        $stats = Cache::remember('landing:stats', now()->addHours(6), function () {
            return [
                'places' => Place::visible()->count(),
                'museums' => Place::visible()->whereHas('category', fn ($q) => $q->where('slug', 'musee'))->count(),
                'monuments' => Place::visible()->whereHas('category', fn ($q) => $q->where('slug', 'monument'))->count(),
                'parks' => Place::visible()->whereHas('category', fn ($q) => $q->where('slug', 'parc-jardin'))->count(),
                'free' => Place::visible()->where('is_free', true)->count(),
                'events' => Place::upcomingEvents()->count(),
            ];
        });

        // Lieux avec photo, par catégorie, pour les collections et les coups de cœur.
        $withPhoto = Cache::remember('landing:with-photo', now()->addHours(3), function () {
            return Place::visible()->with('category')->withAvg('reviews', 'rating')
                ->whereNotNull('cover_image_url')
                ->whereHas('category', fn ($q) => $q->whereIn('slug', ['musee', 'monument', 'parc-jardin', 'lieu-culturel', 'street-art', 'evenement-culturel']))
                ->inRandomOrder()->limit(80)->get();
        });
        $bySlug = $withPhoto->groupBy(fn ($p) => $p->category->slug ?? 'autre');

        $collections = [];
        $define = function (string $key, string $title, string $subtitle, string $filter, $places, string $color, string $icon) use (&$collections, $stats) {
            $places = collect($places)->take(4);
            if ($places->count() < 2) {
                return;
            }
            $collections[] = [
                'key' => $key, 'title' => $title, 'subtitle' => $subtitle, 'filter' => $filter,
                'color' => $color, 'icon' => $icon, 'places' => $places->values(),
            ];
        };
        $free = $withPhoto->filter(fn ($p) => $p->is_free)->values();
        $define('free', 'Gratuit ce week-end', $stats['free'] . ' lieux sans ticket', 'free', $free, '#0F8B8D', 'loyalty');
        if ($advice['indoor']) {
            $define('indoor', 'À l\'abri de la pluie', 'Musées, galeries et scènes', 'musees', $bySlug->get('musee', collect())->merge($bySlug->get('lieu-culturel', collect())), '#7C3AED', 'umbrella');
        } else {
            $define('outdoor', 'Au grand air', 'Parcs, jardins et balades', 'parcs', $bySlug->get('parc-jardin', collect()), '#15803D', 'wb_sunny');
        }
        $define('monuments', 'Grands classiques', $stats['monuments'] . ' monuments et patrimoine', 'monuments', $bySlug->get('monument', collect()), '#B45309', 'account_balance');
        $define('museums', 'Musées à ne pas manquer', $stats['museums'] . ' musées et centres d\'art', 'musees', $bySlug->get('musee', collect()), '#7C3AED', 'palette');
        $define('scenes', 'Scènes & galeries', 'Théâtres, cinémas, galeries', 'culturels', $bySlug->get('lieu-culturel', collect()), '#0369A1', 'theater_comedy');
        $define('events', 'Événements du moment', $stats['events'] . ' dates à venir', 'evenements', Place::upcomingEvents()->with('category')->whereNotNull('cover_image_url')->limit(4)->get(), '#F59E0B', 'celebration');

        $favorites = $withPhoto->sortByDesc(fn ($p) => ((float) ($p->reviews_avg_rating ?? 0)) * 10 + ($p->description ? 2 : 0) + ($p->is_free ? 1 : 0))->take(6)->values();
        $heroPlaces = $favorites->merge($withPhoto->filter(fn ($p) => in_array($p->category->slug ?? null, ['musee', 'monument', 'parc-jardin'], true)))->unique('id')->take(8)->values();
        if ($heroPlaces->count() < 4) {
            $heroPlaces = $withPhoto->take(8)->values();
        }

        return view('home.landing', [
            'forecast' => $forecast,
            'advice' => $advice,
            'stats' => $stats,
            'collections' => $collections,
            'favorites' => $favorites,
            'heroPlaces' => $heroPlaces,
            'events' => Place::upcomingEvents()->with('category')->limit(4)->get(),
            'alerts' => PlaceAlert::active()->with('place')->latest()->limit(4)->get(),
        ]);
    }

    /** Espace personnel. */
    public function index()
    {
        $user = Auth::user();
        $profile = $this->preferences->profile($user);
        $stats = $this->stats->stats($user);
        $level = $this->stats->level($stats['points']);
        $badges = $this->stats->badges($stats);
        $start = config('camino.default_start');
        $forecast = $this->weather->forecast((float) $start['lat'], (float) $start['lng']);
        $advice = $this->weather->advice($forecast);

        $favoriteIds = $user->savedPlaces()->pluck('places.id');
        $topSlugs = array_column($profile['top'], 'slug');
        $recommended = collect();
        $reason = null;
        if ($topSlugs !== []) {
            $recommended = Place::visible()->with('category')->withAvg('reviews', 'rating')
                ->whereHas('category', fn ($q) => $q->whereIn('slug', $topSlugs))
                ->whereNotIn('id', $favoriteIds)->whereNotNull('cover_image_url')
                ->inRandomOrder()->limit(6)->get();
            $reason = 'Parce que tu aimes ' . mb_strtolower(collect($profile['top'])->pluck('name')->take(2)->implode(' et '));
        }
        if ($recommended->isEmpty()) {
            $slugs = $advice['indoor'] ? ['musee', 'lieu-culturel'] : ['parc-jardin', 'monument', 'musee'];
            $recommended = Place::visible()->with('category')->withAvg('reviews', 'rating')
                ->whereHas('category', fn ($q) => $q->whereIn('slug', $slugs))
                ->whereNotIn('id', $favoriteIds)->whereNotNull('cover_image_url')
                ->inRandomOrder()->limit(6)->get();
            $reason = $advice['indoor'] ? 'Sélection à l\'abri, vu la météo' : 'Sélection du jour, météo comprise';
        }

        $lastItinerary = $user->itineraries()->latest()->first();
        $selectionIds = session('itinerary_place_ids', []);
        $selection = $selectionIds ? Place::whereIn('id', $selectionIds)->get() : collect();

        $nextBadge = collect($badges)->where('earned', false)->sortBy('missing')->first();

        $hour = (int) Carbon::now(config('app.timezone'))->format('G');
        $greeting = $hour < 5 ? 'Bonne nuit' : ($hour < 12 ? 'Bonjour' : ($hour < 18 ? 'Bon après-midi' : 'Bonsoir'));

        return view('dashboard', [
            'user' => $user,
            'greeting' => $greeting,
            'profile' => $profile,
            'stats' => $stats,
            'level' => $level,
            'badges' => $badges,
            'nextBadge' => $nextBadge,
            'recommended' => $recommended,
            'reason' => $reason,
            'favorites' => $user->savedPlaces()->with('category')->latest('saved_places.created_at')->limit(6)->get(),
            'itineraries' => $user->itineraries()->latest()->limit(3)->get(),
            'lastItinerary' => $lastItinerary,
            'selection' => $selection,
            'forecast' => $forecast,
            'advice' => $advice,
            'events' => Place::upcomingEvents()->with('category')->limit(4)->get(),
            'alerts' => PlaceAlert::active()->with('place')->latest()->limit(4)->get(),
            'categories' => Category::orderBy('name')->get(),
        ]);
    }
}
