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
        Schema::table('reviews', function (Blueprint $table) {
            if (! Schema::hasColumn('reviews', 'place_id')) {
                $table->foreignId('place_id')->after('id')->constrained()->cascadeOnDelete();
            }

            if (! Schema::hasColumn('reviews', 'user_id')) {
                $table->foreignId('user_id')->after('place_id')->constrained()->cascadeOnDelete();
            }

            if (! Schema::hasColumn('reviews', 'rating')) {
                $table->unsignedTinyInteger('rating')->after('user_id');
            }

            if (! Schema::hasColumn('reviews', 'comment')) {
                $table->text('comment')->after('rating');
            }

            if (! Schema::hasColumn('reviews', 'visited_at')) {
                $table->date('visited_at')->nullable()->after('comment');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            if (Schema::hasColumn('reviews', 'visited_at')) {
                $table->dropColumn('visited_at');
            }

            if (Schema::hasColumn('reviews', 'comment')) {
                $table->dropColumn('comment');
            }

            if (Schema::hasColumn('reviews', 'rating')) {
                $table->dropColumn('rating');
            }

            if (Schema::hasColumn('reviews', 'user_id')) {
                $table->dropConstrainedForeignId('user_id');
            }

            if (Schema::hasColumn('reviews', 'place_id')) {
                $table->dropConstrainedForeignId('place_id');
            }
        });
    }
};

