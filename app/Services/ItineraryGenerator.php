<?php

namespace App\Services;

use App\Models\Place;
use Illuminate\Support\Collection;

class ItineraryGenerator
{
    /**
     * Génère un itinéraire à partir d'une collection de lieux : ordre au plus proche (greedy),
     * durée de visite + temps de marche (4 km/h), estimation du coût par étape.
     *
     * @param \Illuminate\Support\Collection<int,Place> $places
     * @param array{free_only?: bool, budget_eur?: float} $options
     */
    public function generate(
        Collection $places,
        int $timeBudgetMin,
        ?float $startLat = null,
        ?float $startLng = null,
        array $options = []
    ): array {
        $freeOnly = $options['free_only'] ?? false;
        $budgetEur = $options['budget_eur'] ?? null;
        $preserveOrder = $options['preserve_order'] ?? false;

        $places = $places->filter(fn (Place $p) => $p->lat && $p->lng)->values();

        if ($freeOnly) {
            $places = $places->filter(fn (Place $p) => $p->is_free)->values();
        }

        if ($places->isEmpty()) {
            return [
                'title' => 'Parcours CAMINO',
                'mode' => 'walk',
                'totalDurationMin' => 0,
                'totalDistanceKm' => 0,
                'totalBudgetEur' => 0,
                'steps' => [],
                'warnings' => [$freeOnly ? 'Aucun lieu gratuit disponible pour ce périmètre.' : 'Aucun lieu disponible pour ce périmètre.'],
            ];
        }

        $originLat = $startLat ?? (float) $places->avg('lat');
        $originLng = $startLng ?? (float) $places->avg('lng');

        $sorted = $preserveOrder
            ? $places
            : $places->sortBy(function (Place $p) use ($originLat, $originLng) {
                return $this->distanceKm($originLat, $originLng, (float) $p->lat, (float) $p->lng);
            })->values();

        $steps = [];
        $remaining = max($timeBudgetMin, 30);
        $totalDistance = 0.0;
        $totalCost = 0.0;
        $currentLat = $originLat;
        $currentLng = $originLng;
        $order = 1;

        foreach ($sorted as $place) {
            $distKm = $this->distanceKm($currentLat, $currentLng, (float) $place->lat, (float) $place->lng);
            $travelMin = (int) round(($distKm / 4) * 60); // 4 km/h marche
            $visitMin = (int) ($place->visit_duration_min ?: 60);

            if ($remaining - ($travelMin + $visitMin) < 0) {
                break;
            }

            $costEur = $this->estimateCost($place);
            if ($budgetEur !== null && $totalCost + $costEur > $budgetEur) {
                continue;
            }

            $remaining -= ($travelMin + $visitMin);
            $totalDistance += $distKm;
            $totalCost += $costEur;

            $steps[] = [
                'order' => $order++,
                'place_id' => $place->id,
                'title' => $place->title,
                'address' => $place->address ?? 'Adresse à venir',
                'lat' => (float) $place->lat,
                'lng' => (float) $place->lng,
                'visitDurationMin' => $visitMin,
                'travelDurationMin' => $travelMin,
                'distanceKmFromPrevious' => round($distKm, 2),
                'costEur' => round($costEur, 2),
            ];

            $currentLat = (float) $place->lat;
            $currentLng = (float) $place->lng;
        }

        return [
            'title' => 'Parcours CAMINO',
            'mode' => 'walk',
            'totalDurationMin' => $timeBudgetMin - $remaining,
            'totalDistanceKm' => round($totalDistance, 2),
            'totalBudgetEur' => round($totalCost, 2),
            'steps' => $steps,
            'warnings' => $steps === [] ? ['Le temps disponible est trop court pour proposer un parcours.'] : [],
        ];
    }

    /**
     * Estimation du coût d'une étape à partir du lieu (price_level ou is_free).
     */
    public function estimateCost(Place $place): float
    {
        if ($place->is_free) {
            return 0.0;
        }
        // price_level typique : 1 = €, 2 = €€, 3 = €€€
        $level = (int) ($place->price_level ?? 0);
        return match ($level) {
            1 => 5.0,
            2 => 15.0,
            3 => 30.0,
            default => 0.0,
        };
    }

    private function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}

