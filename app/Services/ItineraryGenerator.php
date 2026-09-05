<?php

namespace App\Services;

use App\Models\Place;
use Illuminate\Support\Collection;

class ItineraryGenerator
{
    /** Vitesse de marche retenue (km/h). */
    private const WALK_SPEED_KMH = 4.0;

    /**
     * Génère un itinéraire à pied à partir d'une collection de lieux.
     *
     * Algorithme "plus proche voisin" : depuis le point de départ (ou le barycentre des lieux),
     * on ajoute à chaque étape le lieu le plus proche de la position courante qui tient encore
     * dans le temps et le budget restants. Avec preserve_order, l'ordre fourni est conservé.
     *
     * @param \Illuminate\Support\Collection<int,Place> $places
     * @param array{free_only?: bool, budget_eur?: ?float, preserve_order?: bool} $options
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
            return $this->emptyResult($freeOnly ? 'Aucun lieu gratuit disponible pour ce périmètre.' : 'Aucun lieu disponible pour ce périmètre.');
        }

        $originLat = $startLat ?? (float) $places->avg('lat');
        $originLng = $startLng ?? (float) $places->avg('lng');

        $steps = [];
        $remaining = max($timeBudgetMin, 30);
        $totalDistance = 0.0;
        $totalCost = 0.0;
        $currentLat = $originLat;
        $currentLng = $originLng;
        $order = 1;
        $skippedForBudget = 0;

        /** @var Collection<int,Place> $pool */
        $pool = $places;

        while ($pool->isNotEmpty()) {
            // Candidat suivant : ordre imposé, ou le plus proche de la position courante.
            $candidate = $preserveOrder
                ? $pool->first()
                : $pool->sortBy(fn (Place $p) => $this->distanceKm($currentLat, $currentLng, (float) $p->lat, (float) $p->lng))->first();

            $pool = $pool->reject(fn (Place $p) => $p->id === $candidate->id)->values();

            $distKm = $this->distanceKm($currentLat, $currentLng, (float) $candidate->lat, (float) $candidate->lng);
            $travelMin = (int) round(($distKm / self::WALK_SPEED_KMH) * 60);
            $visitMin = (int) ($candidate->visit_duration_min ?: 60);

            if ($remaining - ($travelMin + $visitMin) < 0) {
                // Ne tient plus dans le temps : en ordre libre on tente les autres, sinon on s'arrête.
                if ($preserveOrder) {
                    break;
                }

                continue;
            }

            $costEur = $this->estimateCost($candidate);
            if ($budgetEur !== null && $totalCost + $costEur > $budgetEur) {
                $skippedForBudget++;

                continue;
            }

            $remaining -= ($travelMin + $visitMin);
            $totalDistance += $distKm;
            $totalCost += $costEur;

            $steps[] = [
                'order' => $order++,
                'place_id' => $candidate->id,
                'title' => $candidate->title,
                'address' => $candidate->address ?? 'Adresse à venir',
                'category' => $candidate->relationLoaded('category') ? $candidate->category?->name : null,
                'lat' => (float) $candidate->lat,
                'lng' => (float) $candidate->lng,
                'visitDurationMin' => $visitMin,
                'travelDurationMin' => $travelMin,
                'distanceKmFromPrevious' => round($distKm, 2),
                'costEur' => round($costEur, 2),
            ];

            $currentLat = (float) $candidate->lat;
            $currentLng = (float) $candidate->lng;
        }

        $warnings = [];
        if ($steps === []) {
            $warnings[] = $skippedForBudget > 0
                ? 'Le budget indiqué est trop faible pour les lieux disponibles.'
                : 'Le temps disponible est trop court pour proposer un parcours.';
        } elseif ($skippedForBudget > 0) {
            $warnings[] = $skippedForBudget . ' lieu(x) écarté(s) pour rester dans le budget.';
        }

        return [
            'title' => 'Parcours CAMINO',
            'mode' => 'walk',
            'start' => ['lat' => $originLat, 'lng' => $originLng],
            'totalDurationMin' => $timeBudgetMin - $remaining,
            'totalDistanceKm' => round($totalDistance, 2),
            'totalBudgetEur' => round($totalCost, 2),
            'steps' => $steps,
            'warnings' => $warnings,
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
        // price_level : 1 = € (≈ 5 €), 2 = €€ (≈ 15 €), 3 = €€€ (≈ 30 €)
        $level = (int) ($place->price_level ?? 0);

        return match ($level) {
            1 => 5.0,
            2 => 15.0,
            3 => 30.0,
            default => 0.0,
        };
    }

    private function emptyResult(string $warning): array
    {
        return [
            'title' => 'Parcours CAMINO',
            'mode' => 'walk',
            'start' => null,
            'totalDurationMin' => 0,
            'totalDistanceKm' => 0,
            'totalBudgetEur' => 0,
            'steps' => [],
            'warnings' => [$warning],
        ];
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
