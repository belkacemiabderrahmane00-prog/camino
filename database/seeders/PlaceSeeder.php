<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Place;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PlaceSeeder extends Seeder
{
    /**
     * Lieux parisiens de démonstration pour la carte CAMINO.
     */
    public function run(): void
    {
        $bySlug = fn (string $slug): ?int => optional(
            Category::where('slug', $slug)->first()
        )->id;

        $places = [
            [
                'title' => 'Musée du Louvre',
                'category_slug' => 'musee',
                'lat' => 48.860611,
                'lng' => 2.337644,
                'address' => 'Rue de Rivoli, 75001 Paris',
                'is_free' => false,
                'price_level' => 3,
                'visit_duration_min' => 150,
                'tags' => ['musée', 'incontournable'],
            ],
            [
                'title' => 'Jardin du Luxembourg',
                'category_slug' => 'parc-jardin',
                'lat' => 48.8466,
                'lng' => 2.3368,
                'address' => '2 Rue Auguste-Comte, 75006 Paris',
                'is_free' => true,
                'price_level' => null,
                'visit_duration_min' => 60,
                'tags' => ['parc', 'nature'],
            ],
            [
                'title' => 'Centre Pompidou',
                'category_slug' => 'musee',
                'lat' => 48.860657,
                'lng' => 2.352095,
                'address' => 'Place Georges-Pompidou, 75004 Paris',
                'is_free' => false,
                'price_level' => 2,
                'visit_duration_min' => 120,
                'tags' => ['art moderne'],
            ],
            [
                'title' => 'Parc des Buttes-Chaumont',
                'category_slug' => 'parc-jardin',
                'lat' => 48.88111,
                'lng' => 2.38306,
                'address' => '1 Rue Botzaris, 75019 Paris',
                'is_free' => true,
                'price_level' => null,
                'visit_duration_min' => 75,
                'tags' => ['parc', 'vue'],
            ],
            [
                'title' => 'Fondation Louis Vuitton',
                'category_slug' => 'musee',
                'lat' => 48.87667,
                'lng' => 2.26333,
                'address' => '8 Avenue du Mahatma Gandhi, 75116 Paris',
                'is_free' => false,
                'price_level' => 3,
                'visit_duration_min' => 120,
                'tags' => ['art contemporain'],
            ],
            [
                'title' => 'Rue Dénoyez – Street art Belleville',
                'category_slug' => 'street-art',
                'lat' => 48.871668,
                'lng' => 2.378529,
                'address' => 'Rue Dénoyez, 75020 Paris',
                'is_free' => true,
                'price_level' => null,
                'visit_duration_min' => 45,
                'tags' => ['street art'],
            ],
        ];

        foreach ($places as $data) {
            $categoryId = $bySlug($data['category_slug']) ?? $bySlug('lieu-culturel');

            Place::updateOrCreate(
                ['title' => $data['title'], 'address' => $data['address']],
                [
                    'slug' => Str::slug($data['title']),
                    'description' => null,
                    'category_id' => $categoryId,
                    'lat' => $data['lat'],
                    'lng' => $data['lng'],
                    'address' => $data['address'],
                    'status' => 'approved',
                    'is_free' => $data['is_free'],
                    'price_level' => $data['price_level'],
                    'visit_duration_min' => $data['visit_duration_min'],
                    'opening_hours' => null,
                    'tags' => $data['tags'],
                    'cover_image_url' => null,
                    'gallery' => [],
                    'sources' => ['seed' => 'demo'],
                    'external_id' => null,
                ]
            );
        }
    }
}

