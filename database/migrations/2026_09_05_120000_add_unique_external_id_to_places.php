<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Index unique sur external_id : permet l'upsert par lots à l'ingestion
     * DATAtourisme (au lieu d'un SELECT + INSERT/UPDATE par lieu).
     * Les NULL (lieux de démo / manuels) restent autorisés en plusieurs exemplaires.
     */
    public function up(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->unique('external_id', 'places_external_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->dropUnique('places_external_id_unique');
        });
    }
};
