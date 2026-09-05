<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Données de base : catégories + lieux de démonstration.
     * (Aucun compte utilisateur n'est créé ici : inscription via /register.)
     */
    public function run(): void
    {
        $this->call([
            DemoDataSeeder::class,
        ]);
    }
}
