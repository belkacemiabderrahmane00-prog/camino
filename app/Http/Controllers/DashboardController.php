<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Place;
use App\Models\PlaceAlert;
use App\Services\UserPreferenceService;
use App\Services\WeatherService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function __construct(
        private readonly WeatherService $weather,
        private readonly UserPreferenceService $preferences,
    ) {}

    /** Page publique d'accueil. */
    public function landing()
    {
        $start = config('camino.default_start');
        $forecast = $this->weather->forecast((float) $start['lat'], (float) $start['lng']);

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

        $featured = Cache::remember('landing:featured', now()->addHours(3), function () {
            return Place::visible()->with('category')->withAvg('reviews', 'rating')
                ->whereNotNull('cover_image_url')
                ->whereHas('category', fn ($q) => $q->whereIn('slug', ['musee', 'monument', 'parc-jardin', 'lieu-culturel']))
                ->inRandomOrder()->limit(8)->get();
        });

        $events = Place::upcomingEvents()->with('category')->limit(4)->get();

        return view('home.landing', [
            'forecast' => $forecast,
            'stats' => $stats,
            'featured' => $featured,
            'events' => $events,
            'alerts' => PlaceAlert::active()->with('place')->latest()->limit(4)->get(),
        ]);
    }

    /** Espace personnel. */
    public function index()
    {
        $user = Auth::user();
        $profile = $this->preferences->profile($user);
        $start = config('camino.default_start');

        $recommended = collect();
        $topSlugs = array_column($profile['top'], 'slug');
        if ($topSlugs !== []) {
            $favoriteIds = $user->savedPlaces()->pluck('places.id');
            $recommended = Place::visible()->with('category')->withAvg('reviews', 'rating')
                ->whereHas('category', fn ($q) => $q->whereIn('slug', $topSlugs))
                ->whereNotIn('id', $favoriteIds)
                ->whereNotNull('cover_image_url')
                ->inRandomOrder()->limit(6)->get();
        }
        if ($recommended->isEmpty()) {
            $recommended = Place::visible()->with('category')->whereNotNull('cover_image_url')
                ->whereHas('category', fn ($q) => $q->whereIn('slug', ['musee', 'monument', 'parc-jardin']))
                ->inRandomOrder()->limit(6)->get();
        }

        return view('dashboard', [
            'profile' => $profile,
            'recommended' => $recommended,
            'favorites' => $user->savedPlaces()->with('category')->latest('saved_places.created_at')->limit(4)->get(),
            'itineraries' => $user->itineraries()->latest()->limit(3)->get(),
            'forecast' => $this->weather->forecast((float) $start['lat'], (float) $start['lng']),
            'events' => Place::upcomingEvents()->with('category')->limit(3)->get(),
            'categories' => Category::orderBy('name')->get(),
        ]);
    }
}
