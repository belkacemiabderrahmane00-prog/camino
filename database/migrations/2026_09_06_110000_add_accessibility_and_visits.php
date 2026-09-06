<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('places', function (Blueprint $table) {
            if (! Schema::hasColumn('places', 'accessible')) {
                // null = inconnu, true = accessible PMR (entrée de plain-pied ou rampe), false = non accessible
                $table->boolean('accessible')->nullable()->after('opening_hours');
                $table->string('accessibility_source', 40)->nullable()->after('accessible');
                $table->string('accessibility_note', 255)->nullable()->after('accessibility_source');
            }
        });

        if (! Schema::hasTable('visits')) {
            Schema::create('visits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('place_id')->constrained()->cascadeOnDelete();
                $table->foreignId('itinerary_id')->nullable()->constrained()->nullOnDelete();
                $table->string('source', 20)->default('guidage'); // guidage | manuel
                $table->unsignedSmallInteger('minutes')->nullable();
                $table->timestamp('visited_at');
                $table->timestamps();
                $table->index(['user_id', 'visited_at']);
                $table->unique(['user_id', 'place_id', 'visited_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('visits');
        Schema::table('places', function (Blueprint $table) {
            foreach (['accessible', 'accessibility_source', 'accessibility_note'] as $col) {
                if (Schema::hasColumn('places', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
