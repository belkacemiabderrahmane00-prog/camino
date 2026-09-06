<?php

namespace App\Services;

use App\Models\User;

/**
 * Statistiques, points, niveau et badges d'un utilisateur (profil et espace perso).
 */
class UserStatsService
{
    /** Paliers de niveau (points cumulés). */
    public const LEVELS = [
        [0, 'Curieux', 'explore'],
        [30, 'Flâneur', 'directions_walk'],
        [80, 'Explorateur', 'hiking'],
        [180, 'Guide local', 'tour'],
        [400, 'Légende du quartier', 'military_tech'],
    ];

    /** @return array{itineraries:int,km:float,favorites:int,reviews:int,photos:int,alerts:int,places:int,points:int} */
    public function stats(User $user): array
    {
        $itineraries = $user->itineraries()->get(['result_json']);
        $km = round($itineraries->sum(fn ($i) => (float) ($i->result_json['total_distance_km'] ?? 0)), 1);
        $counts = [
            'itineraries' => $itineraries->count(),
            'km' => $km,
            'favorites' => $user->savedPlaces()->count(),
            'reviews' => $user->reviews()->count(),
            'photos' => $user->photos()->where('status', 'approved')->count(),
            'alerts' => $user->alerts()->count(),
            'places' => $user->submittedPlaces()->where('status', 'approved')->count(),
            'visits' => $user->visits()->count(),
        ];
        $counts['points'] = (int) ($counts['itineraries'] * 10 + $counts['reviews'] * 5 + $counts['photos'] * 8 + $counts['alerts'] * 3 + $counts['favorites'] + $counts['places'] * 15 + $counts['visits'] * 4 + $km);

        return $counts;
    }

    /** @return array{index:int,name:string,icon:string,points:int,next:?int,progress:int} */
    public function level(int $points): array
    {
        $current = 0;
        foreach (self::LEVELS as $i => [$threshold]) {
            if ($points >= $threshold) {
                $current = $i;
            }
        }
        $next = self::LEVELS[$current + 1][0] ?? null;
        $base = self::LEVELS[$current][0];

        return [
            'index' => $current + 1,
            'name' => self::LEVELS[$current][1],
            'icon' => self::LEVELS[$current][2],
            'points' => $points,
            'next' => $next,
            'progress' => $next ? (int) round(($points - $base) / ($next - $base) * 100) : 100,
        ];
    }

    /**
     * Badges avec état et "prochaine marche" (pour les coups de pouce de l'espace perso).
     *
     * @return array<int,array{key:string,name:string,icon:string,earned:bool,hint:string,missing:int}>
     */
    public function badges(array $stats): array
    {
        $defs = [
            ['first_route', 'Premier pas', 'flag', 'itineraries', 1, 'parcours généré'],
            ['walker', 'Marcheur', 'directions_walk', 'km', 10, 'km parcourus'],
            ['collector', 'Collectionneur', 'favorite', 'favorites', 5, 'favoris'],
            ['critic', 'Critique', 'rate_review', 'reviews', 3, 'avis publiés'],
            ['reporter', 'Reporter', 'photo_camera', 'photos', 1, 'photo publiée'],
            ['lookout', 'Vigie', 'campaign', 'alerts', 1, 'alerte signalée'],
            ['explorer', 'Sur le terrain', 'footprint', 'visits', 5, 'lieux visités'],
        ];
        $out = [];
        foreach ($defs as [$key, $name, $icon, $stat, $target, $label]) {
            $value = (float) ($stats[$stat] ?? 0);
            $out[] = [
                'key' => $key,
                'name' => $name,
                'icon' => $icon,
                'earned' => $value >= $target,
                'hint' => $target . ' ' . $label,
                'missing' => max(0, (int) ceil($target - $value)),
                'label' => $label,
                'target' => $target,
                'value' => $value,
                'progress' => (int) min(100, round($value / max(1, $target) * 100)),
            ];
        }

        return $out;
    }
}
