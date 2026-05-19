<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Utilisateur de test
        User::firstOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User']
        );

        // Données de démonstration (catégories + lieux)
        $this->call([
            DemoDataSeeder::class,
        ]);
    }
}
