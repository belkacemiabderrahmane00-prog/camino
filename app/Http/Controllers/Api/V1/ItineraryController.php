<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Place;
use App\Services\ItineraryGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ItineraryController extends Controller
{
    /**
     * POST /api/v1/itineraries/generate
     * time_budget_min, budget_eur, mode (walk|bike), start_lat, start_lng, radius_km,
     * interests (slugs séparés par des virgules), free (bool), starts_at (ISO 8601).
     */
    public function generate(Request $request, ItineraryGenerator $generator): JsonResponse
    {
        $data = $request->validate([
            'time_budget_min' => ['required', 'integer', 'min:30', 'max:720'],
            'budget_eur' => ['nullable', 'numeric', 'min:0'],
            'mode' => ['nullable', 'in:walk,bike'],
            'interests' => ['nullable', 'string'],
            'free' => ['nullable', 'boolean'],
            'start_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'start_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'radius_km' => ['nullable', 'integer', 'min:1', 'max:30'],
            'starts_at' => ['nullable', 'date'],
        ]);

        $default = config('camino.default_start');
        $start = [
            'lat' => (float) ($data['start_lat'] ?? $default['lat']),
            'lng' => (float) ($data['start_lng'] ?? $default['lng']),
            'label' => isset($data['start_lat']) ? 'Point de départ' : $default['label'],
        ];
        $radiusKm = (int) ($data['radius_km'] ?? 4);
        $interests = array_values(array_filter(array_map('trim', explode(',', (string) ($data['interests'] ?? '')))));

        $dLat = $radiusKm / 111;
        $dLng = $radiusKm / 73;
        $query = Place::query()->with('category')->withAvg('reviews', 'rating')->visible()
            ->whereBetween('lat', [$start['lat'] - $dLat, $start['lat'] + $dLat])
            ->whereBetween('lng', [$start['lng'] - $dLng, $start['lng'] + $dLng]);
        if ($interests !== []) {
            $query->whereHas('category', fn ($q) => $q->whereIn('slug', $interests));
        }
        if (! empty($data['free'])) {
            $query->where('is_free', true);
        }
        $candidates = $query->limit(600)->get()
            ->sortBy(fn (Place $p) => (($p->lat - $start['lat']) ** 2 + (($p->lng - $start['lng']) * 0.66) ** 2))
            ->take(80)->values();

        $result = $generator->generate($candidates, [
            'time_budget_min' => (int) $data['time_budget_min'],
            'budget_eur' => isset($data['budget_eur']) ? (float) $data['budget_eur'] : null,
            'free_only' => ! empty($data['free']),
            'mode' => $data['mode'] ?? 'walk',
            'start' => $start,
            'starts_at' => ! empty($data['starts_at']) ? Carbon::parse($data['starts_at'], config('app.timezone')) : null,
            'interests' => $interests,
        ]);

        return response()->json($result);
    }
}
