<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('poi_media')) {
            return;
        }

        Schema::table('poi_media', function (Blueprint $table) {
            // Passer les URLs d'image en TEXT pour supporter les URLs longues (Wikipedia, Commons, etc.)
            $table->text('image_url_original')->change();
            $table->text('image_url_thumb')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('poi_media')) {
            return;
        }

        Schema::table('poi_media', function (Blueprint $table) {
            // Revenir à string par défaut (255). Attention: possible troncature si rollback après insertion d'URLs longues.
            $table->string('image_url_original')->change();
            $table->string('image_url_thumb')->nullable()->change();
        });
    }
};

