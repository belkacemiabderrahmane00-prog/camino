<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('itineraries', function (Blueprint $table) {
            if (! Schema::hasColumn('itineraries', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            }
            if (! Schema::hasColumn('itineraries', 'name')) {
                $table->string('name', 120)->default('Mon parcours')->after('user_id');
            }
            if (! Schema::hasColumn('itineraries', 'result_json')) {
                $table->json('result_json')->nullable()->after('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('itineraries', function (Blueprint $table) {
            if (Schema::hasColumn('itineraries', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            }
            if (Schema::hasColumn('itineraries', 'name')) {
                $table->dropColumn('name');
            }
            if (Schema::hasColumn('itineraries', 'result_json')) {
                $table->dropColumn('result_json');
            }
        });
    }
};
