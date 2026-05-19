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
            if (! Schema::hasColumn('places', 'cover_image_source')) {
                $table->string('cover_image_source')->nullable()->after('cover_image_url');
            }
            if (! Schema::hasColumn('places', 'cover_image_license')) {
                $table->string('cover_image_license')->nullable()->after('cover_image_source');
            }
            if (! Schema::hasColumn('places', 'cover_image_author')) {
                $table->string('cover_image_author')->nullable()->after('cover_image_license');
            }
            if (! Schema::hasColumn('places', 'cover_image_attribution')) {
                $table->text('cover_image_attribution')->nullable()->after('cover_image_author');
            }
            if (! Schema::hasColumn('places', 'cover_image_page_url')) {
                $table->string('cover_image_page_url')->nullable()->after('cover_image_attribution');
            }
            if (! Schema::hasColumn('places', 'wikidata_qid')) {
                $table->string('wikidata_qid')->nullable()->after('external_id');
            }
        });

        if (Schema::hasColumn('places', 'wikidata_qid')) {
            try {
                Schema::table('places', function (Blueprint $table) {
                    $table->index('wikidata_qid');
                });
            } catch (\Throwable) {
                // Index déjà présent ou erreur silencieuse acceptable
            }
        }
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
            foreach ([
                'cover_image_source',
                'cover_image_license',
                'cover_image_author',
                'cover_image_attribution',
                'cover_image_page_url',
                'wikidata_qid',
            ] as $col) {
                if (Schema::hasColumn('places', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

