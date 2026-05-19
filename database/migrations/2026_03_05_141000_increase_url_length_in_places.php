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
        if (! Schema::hasTable('places')) {
            return;
        }

        Schema::table('places', function (Blueprint $table) {
            // Passer les URLs de cover en TEXT pour supporter les URLs longues (Wikipedia, Commons, etc.)
            if (Schema::hasColumn('places', 'cover_image_url')) {
                $table->text('cover_image_url')->nullable()->change();
            }
            if (Schema::hasColumn('places', 'cover_image_page_url')) {
                $table->text('cover_image_page_url')->nullable()->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('places')) {
            return;
        }

        Schema::table('places', function (Blueprint $table) {
            // Revenir à string par défaut (255). Attention: possible troncature si rollback après insertion d'URLs longues.
            if (Schema::hasColumn('places', 'cover_image_url')) {
                $table->string('cover_image_url')->nullable()->change();
            }
            if (Schema::hasColumn('places', 'cover_image_page_url')) {
                $table->string('cover_image_page_url')->nullable()->change();
            }
        });
    }
};

