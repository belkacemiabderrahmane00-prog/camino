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
        Schema::table('saved_places', function (Blueprint $table) {
            if (! Schema::hasColumn('saved_places', 'user_id')) {
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            }

            if (! Schema::hasColumn('saved_places', 'place_id')) {
                $table->foreignId('place_id')->constrained()->cascadeOnDelete();
            }

            $table->unique(['user_id', 'place_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('saved_places', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'place_id']);

            if (Schema::hasColumn('saved_places', 'place_id')) {
                $table->dropConstrainedForeignId('place_id');
            }

            if (Schema::hasColumn('saved_places', 'user_id')) {
                $table->dropConstrainedForeignId('user_id');
            }
        });
    }
};

