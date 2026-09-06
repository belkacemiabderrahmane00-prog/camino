<?php

namespace App\Services;

/**
 * Couleur d'une catégorie (alignée avec resources/js/app.js et tailwind.config.js), pour les rendus côté serveur.
 */
class ColorHelper
{
    private const COLORS = [
        'musee' => '#7C3AED', 'monument' => '#B45309', 'parc-jardin' => '#15803D', 'lieu-culturel' => '#0369A1', 'restauration' => '#DB2777',
        'evenement-culturel' => '#F59E0B', 'street-art' => '#E11D48', 'itineraire' => '#0F766E', 'librairies-bibliotheques' => '#1D4ED8', 'ateliers-artisans' => '#9A3412',
    ];

    public static function forSlug(?string $slug): string
    {
        return self::COLORS[$slug ?? ''] ?? '#0F8B8D';
    }
}
