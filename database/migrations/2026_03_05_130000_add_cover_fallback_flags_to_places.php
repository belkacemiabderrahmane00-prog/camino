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
            if (! Schema::hasColumn('places', 'cover_image_is_fallback')) {
                $table->boolean('cover_image_is_fallback')->default(false)->after('cover_image_page_url');
            }
            if (! Schema::hasColumn('places', 'cover_image_fallback_reason')) {
                $table->string('cover_image_fallback_reason')->nullable()->after('cover_image_is_fallback');
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
            foreach (['cover_image_is_fallback', 'cover_image_fallback_reason'] as $col) {
                if (Schema::hasColumn('places', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

