<?php

namespace App\Services;

use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Profil culturel personnalisé (brief client) : poids par catégorie déduits des favoris,
 * des avis et des parcours réalisés. Les poids vont de 0 à 1 et servent au scoring du générateur.
 */
class UserPreferenceService
{
    /**
     * @return array{weights:array<string,float>,top:array<int,array{slug:string,name:string,weight:float}>,signals:array{favorites:int,reviews:int,itineraries:int}}
     */
    public function profile(?User $user): array
    {
        if (! $user) {
            return ['weights' => [], 'top' => [], 'signals' => ['favorites' => 0, 'reviews' => 0, 'itineraries' => 0]];
        }

        $scores = [];

        // Favoris : signal fort.
        $favorites = DB::table('saved_places')
            ->join('places', 'places.id', '=', 'saved_places.place_id')
            ->join('categories', 'categories.id', '=', 'places.category_id')
            ->where('saved_places.user_id', $user->id)
            ->selectRaw('categories.slug, count(*) as c')
            ->groupBy('categories.slug')
            ->pluck('c', 'slug');
        foreach ($favorites as $slug => $c) {
            $scores[$slug] = ($scores[$slug] ?? 0) + 3 * $c;
        }

        // Avis : pondérés par la note (une mauvaise note n'est pas un intérêt).
        $reviews = DB::table('reviews')
            ->join('places', 'places.id', '=', 'reviews.place_id')
            ->join('categories', 'categories.id', '=', 'places.category_id')
            ->where('reviews.user_id', $user->id)
            ->selectRaw('categories.slug, sum(reviews.rating - 2.5) as s, count(*) as c')
            ->groupBy('categories.slug')
            ->get();
        foreach ($reviews as $row) {
            $scores[$row->slug] = ($scores[$row->slug] ?? 0) + (float) $row->s;
        }

        // Parcours générés : catégories des étapes.
        $itineraries = $user->itineraries()->latest()->limit(20)->get();
        foreach ($itineraries as $itinerary) {
            foreach ($itinerary->result_json['steps'] ?? [] as $step) {
                $slug = $step['category_slug'] ?? null;
                if ($slug) {
                    $scores[$slug] = ($scores[$slug] ?? 0) + 1;
                }
            }
        }

        $max = $scores === [] ? 0 : max(array_map('abs', $scores));
        $weights = [];
        foreach ($scores as $slug => $score) {
            $weights[$slug] = $max > 0 ? round(max(-1, min(1, $score / $max)), 2) : 0;
        }
        arsort($weights);

        $names = Category::whereIn('slug', array_keys($weights))->pluck('name', 'slug');
        $top = [];
        foreach (array_slice($weights, 0, 3, true) as $slug => $w) {
            if ($w > 0) {
                $top[] = ['slug' => $slug, 'name' => $names[$slug] ?? $slug, 'weight' => $w];
            }
        }

        return [
            'weights' => $weights,
            'top' => $top,
            'signals' => [
                'favorites' => (int) $favorites->sum(),
                'reviews' => (int) $reviews->sum('c'),
                'itineraries' => $itineraries->count(),
            ],
        ];
    }
}
