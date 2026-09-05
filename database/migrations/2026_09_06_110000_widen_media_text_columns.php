<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Les métadonnées Wikimedia (auteur, licence, URL d'attribution, titre) dépassent parfois 255 caractères.
     */
    public function up(): void
    {
        Schema::table('poi_media', function (Blueprint $table) {
            $table->text('title')->nullable()->change();
            $table->text('license')->nullable()->change();
            $table->text('author')->nullable()->change();
            $table->text('attribution_url')->nullable()->change();
        });

        Schema::table('places', function (Blueprint $table) {
            $table->text('cover_image_license')->nullable()->change();
            $table->text('cover_image_author')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('poi_media', function (Blueprint $table) {
            $table->string('title')->nullable()->change();
            $table->string('license')->nullable()->change();
            $table->string('author')->nullable()->change();
            $table->string('attribution_url')->nullable()->change();
        });

        Schema::table('places', function (Blueprint $table) {
            $table->string('cover_image_license')->nullable()->change();
            $table->string('cover_image_author')->nullable()->change();
        });
    }
};
