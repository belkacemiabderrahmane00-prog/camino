<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Profil enrichi : photo (stockée en base, redimensionnée), bio, centres d'intérêt, mobilité préférée. */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->binary('avatar')->nullable()->after('is_admin');
            $table->string('avatar_mime', 32)->nullable()->after('avatar');
            $table->string('bio', 280)->nullable()->after('avatar_mime');
            $table->json('interests')->nullable()->after('bio');
            $table->string('mobility', 8)->default('walk')->after('interests');
            $table->string('city', 80)->nullable()->after('mobility');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['avatar', 'avatar_mime', 'bio', 'interests', 'mobility', 'city']);
        });
    }
};
