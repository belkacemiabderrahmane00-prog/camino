<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePlaceReportRequest;
use App\Models\Place;
use App\Models\PlaceReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PlaceController extends Controller
{
    public function show(Place $place, Request $request)
    {
        $place->load([
            'category',
            'media' => fn ($q) => $q->where('is_cover', true)->limit(1),
            'alerts' => fn ($q) => $q->active()->latest(),
            'photos' => fn ($q) => $q->approved()->latest()->limit(12),
        ]);

        $nearby = Place::visible()->with('category')
            ->where('id', '!=', $place->id)
            ->whereBetween('lat', [$place->lat - 0.012, $place->lat + 0.012])
            ->whereBetween('lng', [$place->lng - 0.018, $place->lng + 0.018])
            ->whereHas('category', fn ($q) => $q->where('slug', '!=', 'restauration'))
            ->orderByRaw('(cover_image_url is null) asc')
            ->limit(6)->get();
        $isFavorite = false;

        if (Auth::check()) {
            $isFavorite = Auth::user()
                ->savedPlaces()
                ->where('place_id', $place->id)
                ->exists();
        }

        $reviewsQuery = $place->reviews()->with('user')->latest();
        $reviewCount = $reviewsQuery->count();
        $reviews = $reviewsQuery->paginate(5, ['*'], 'avis');
        $averageRating = $reviewCount > 0
            ? round((float) $reviewsQuery->avg('rating'), 1)
            : null;

        $itineraryPlaceIds = session('itinerary_place_ids', []);
        $isInItinerary = in_array($place->id, $itineraryPlaceIds);

        return view('places.show', [
            'place' => $place,
            'isFavorite' => $isFavorite,
            'isInItinerary' => $isInItinerary,
            'reviews' => $reviews,
            'reviewCount' => $reviewCount,
            'averageRating' => $averageRating,
            'nearby' => $nearby,
            'alertTypes' => \App\Models\PlaceAlert::TYPES,
        ]);
    }

    public function favorites(Request $request)
    {
        $user = Auth::user();

        $query = $user
            ? $user->savedPlaces()->with('category')->latest()
            : null;

        $categoryId = $request->integer('category_id');
        if ($query && $categoryId > 0) {
            $query->whereHas('category', fn ($q) => $q->where('id', $categoryId));
        }

        $search = $request->filled('q') ? trim($request->string('q')->toString()) : '';
        if ($query && $search !== '') {
            $query->search($search);
        }

        $places = $query ? $query->get() : collect();
        $totalFavorites = $user ? $user->savedPlaces()->count() : 0;
        $categories = \App\Models\Category::orderBy('name')->get();

        return view('places.favorites', [
            'places' => $places,
            'categories' => $categories,
            'selectedCategoryId' => $categoryId,
            'totalFavorites' => $totalFavorites,
            'search' => $search,
        ]);
    }

    public function toggleFavorite(Place $place, Request $request)
    {
        $user = Auth::user();

        if (! $user) {
            abort(403);
        }

        $relationship = $user->savedPlaces();

        $alreadyFavorite = $relationship
            ->where('place_id', $place->id)
            ->exists();

        if ($alreadyFavorite) {
            $relationship->detach($place->id);
            $status = 'removed';
        } else {
            $relationship->attach($place->id);
            $status = 'added';
        }

        return back()->with('favorite_status', $status);
    }

    public function report(StorePlaceReportRequest $request, Place $place)
    {
        $validated = $request->validated();

        PlaceReport::create([
            'place_id' => $place->id,
            'user_id' => Auth::id(),
            'reason' => $validated['reason'],
            'message' => $validated['message'] ?? null,
        ]);

        return back()->with('status', 'Merci, ton signalement a bien été envoyé. L’équipe CAMINO en prend connaissance.');
    }
}

