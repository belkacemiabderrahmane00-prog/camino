<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Place;
use App\Services\ItineraryGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItineraryController extends Controller
{
    public function generate(Request $request, ItineraryGenerator $generator): JsonResponse
    {
        $data = $request->validate([
            'time_budget_min' => ['required', 'integer', 'min:30', 'max:600'],
            'mode' => ['nullable', 'in:walk'],
            'bbox' => ['nullable', 'string'],
            'category_slugs' => ['nullable', 'string'],
            'free' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:120'],
            'start_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'start_lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $query = Place::query()
            ->with('category')
            ->approved()
            ->whereNotNull('lat')
            ->whereNotNull('lng');

        if (! empty($data['bbox'])) {
            $coords = array_map('floatval', explode(',', $data['bbox']));
            if (count($coords) === 4) {
                [$minLat, $minLng, $maxLat, $maxLng] = $coords;
                $query->whereBetween('lat', [$minLat, $maxLat])
                    ->whereBetween('lng', [$minLng, $maxLng]);
            }
        }

        if (! empty($data['category_slugs'])) {
            $slugList = array_filter(array_map('trim', explode(',', $data['category_slugs'])));
            if ($slugList !== []) {
                $categoryIds = Category::query()
                    ->whereIn('slug', $slugList)
                    ->pluck('id')
                    ->all();

                if ($categoryIds !== []) {
                    $query->whereIn('category_id', $categoryIds);
                }
            }
        }

        if (! empty($data['q'])) {
            $search = trim($data['q']);
            $query->search($search);
        }

        if (! empty($data['free'])) {
            $query->where('is_free', true);
        }

        $candidates = $query->latest()->take(40)->get();

        $result = $generator->generate(
            $candidates,
            (int) $data['time_budget_min'],
            $data['start_lat'] ?? null,
            $data['start_lng'] ?? null,
            [
                'free_only' => ! empty($data['free']),
                'budget_eur' => null,
            ]
        );

        return response()->json($result);
    }
}

