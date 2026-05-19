<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Corrige les colonnes manquantes sur categories (name, slug).
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            if (! Schema::hasColumn('categories', 'name')) {
                $table->string('name')->after('id');
            }
            if (! Schema::hasColumn('categories', 'slug')) {
                $table->string('slug')->nullable()->unique()->after('name');
            }
        });
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
    }
};
