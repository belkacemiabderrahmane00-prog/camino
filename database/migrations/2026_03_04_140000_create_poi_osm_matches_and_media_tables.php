<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('poi_osm_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id')->constrained()->cascadeOnDelete();
            $table->string('osm_type', 16); // node, way, relation
            $table->bigInteger('osm_id');
            $table->string('wikidata_qid', 32)->nullable();
            $table->float('match_score')->nullable();
            $table->timestamp('matched_at')->nullable();
            $table->timestamps();

            $table->unique(['place_id']);
            $table->index(['osm_type', 'osm_id']);
            $table->index('wikidata_qid');
        });

        Schema::create('poi_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id')->constrained()->cascadeOnDelete();
            $table->string('source', 32); // wikimedia_commons, manual, etc.
            $table->string('title')->nullable();
            $table->string('image_url_original');
            $table->string('image_url_thumb')->nullable();
            $table->string('license')->nullable();
            $table->string('author')->nullable();
            $table->string('attribution_url')->nullable();
            $table->boolean('is_cover')->default(false);
            $table->json('extra')->nullable();
            $table->timestamps();

            $table->index(['place_id', 'is_cover']);
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poi_media');
        Schema::dropIfExists('poi_osm_matches');
    }
};

