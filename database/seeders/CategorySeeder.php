<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Catégories de base pour CAMINO (alignées avec les filtres de la carte).
     */
    public function run(): void
    {
        $categories = [
            'Musée',
            'Monument',
            'Parc / Jardin',
            'Street Art',
            'Lieu culturel',
            'Restauration',
            'Itinéraire',
            'Événement culturel',
        ];

        foreach ($categories as $name) {
            Category::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name]
            );
        }
    }
}

