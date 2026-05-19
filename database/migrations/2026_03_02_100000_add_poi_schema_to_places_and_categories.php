<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Schéma POI unifié CAMINO (Île-de-France).
     * Compatible ingestion DATAtourisme + enrichissements (Photos Région IDF, Paris Musées).
     */
    public function up(): void
    {
        // Categories : nom pour affichage + slug pour filtres
        if (! Schema::hasColumn('categories', 'name')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->string('name')->after('id');
                $table->string('slug')->nullable()->unique()->after('name');
            });
        }

        // Places : champs POI
        $places = function (Blueprint $table) {
            if (! Schema::hasColumn('places', 'title')) {
                $table->string('title')->after('id');
            }
            if (! Schema::hasColumn('places', 'slug')) {
                $table->string('slug')->nullable()->after('title');
            }
            if (! Schema::hasColumn('places', 'description')) {
                $table->text('description')->nullable()->after('slug');
            }
            if (! Schema::hasColumn('places', 'category_id')) {
                $table->foreignId('category_id')->nullable()->after('description')->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('places', 'lat')) {
                $table->decimal('lat', 10, 7)->nullable()->after('category_id');
            }
            if (! Schema::hasColumn('places', 'lng')) {
                $table->decimal('lng', 10, 7)->nullable()->after('lat');
            }
            if (! Schema::hasColumn('places', 'address')) {
                $table->string('address')->nullable()->after('lng');
            }
            if (! Schema::hasColumn('places', 'status')) {
                $table->string('status', 20)->default('draft')->after('address');
            }
            if (! Schema::hasColumn('places', 'is_free')) {
                $table->boolean('is_free')->default(false)->after('status');
            }
            if (! Schema::hasColumn('places', 'price_level')) {
                $table->unsignedTinyInteger('price_level')->nullable()->after('is_free');
            }
            if (! Schema::hasColumn('places', 'visit_duration_min')) {
                $table->unsignedInteger('visit_duration_min')->nullable()->after('price_level');
            }
            if (! Schema::hasColumn('places', 'opening_hours')) {
                $table->json('opening_hours')->nullable()->after('visit_duration_min');
            }
            if (! Schema::hasColumn('places', 'tags')) {
                $table->json('tags')->nullable()->after('opening_hours');
            }
            if (! Schema::hasColumn('places', 'cover_image_url')) {
                $table->string('cover_image_url')->nullable()->after('tags');
            }
            if (! Schema::hasColumn('places', 'gallery')) {
                $table->json('gallery')->nullable()->after('cover_image_url');
            }
            if (! Schema::hasColumn('places', 'sources')) {
                $table->json('sources')->nullable()->after('gallery');
            }
            if (! Schema::hasColumn('places', 'external_id')) {
                $table->string('external_id')->nullable()->after('sources');
            }
        };

        Schema::table('places', $places);

        // Index pour déduplication à l'ingestion (upsert par external_id)
        if (Schema::hasColumn('places', 'external_id')) {
            try {
                Schema::table('places', function (Blueprint $table) {
                    $table->index('external_id');
                });
            } catch (\Throwable) {
                // Index déjà présent (migration partielle ou ré-exécution)
            }
        }
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            if (Schema::hasColumn('categories', 'slug')) {
                $table->dropColumn('slug');
            }
            if (Schema::hasColumn('categories', 'name')) {
                $table->dropColumn('name');
            }
        });

        $columns = [
            'title', 'slug', 'description', 'category_id', 'lat', 'lng', 'address',
            'status', 'is_free', 'price_level', 'visit_duration_min', 'opening_hours',
            'tags', 'cover_image_url', 'gallery', 'sources', 'external_id',
        ];
        Schema::table('places', function (Blueprint $table) use ($columns) {
            foreach ($columns as $col) {
                if (Schema::hasColumn('places', $col)) {
                    if ($col === 'category_id') {
                        $table->dropConstrainedForeignId('category_id');
                    } else {
                        $table->dropColumn($col);
                    }
                }
            }
        });
    }

};
