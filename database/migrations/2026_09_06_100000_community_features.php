<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fonctionnalités communautaires (brief client) : événements datés, lieux proposés par
     * les utilisateurs, alertes façon Waze (événement gratuit, affluence, fermeture), photos partagées,
     * administrateurs pour la modération.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('password');
        });

        Schema::table('places', function (Blueprint $table) {
            $table->date('event_start_at')->nullable()->after('visit_duration_min');
            $table->date('event_end_at')->nullable()->after('event_start_at');
            $table->foreignId('created_by')->nullable()->after('external_id')->constrained('users')->nullOnDelete();
            $table->index(['status', 'category_id']);
            $table->index('event_end_at');
        });

        Schema::create('place_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 24); // free_event | crowd | closure | info
            $table->string('title', 120);
            $table->text('message')->nullable();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at');
            $table->string('status', 16)->default('active'); // active | hidden
            $table->timestamps();
            $table->index(['status', 'expires_at']);
            $table->index(['lat', 'lng']);
        });

        Schema::create('place_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('caption', 160)->nullable();
            $table->string('mime', 32);
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->unsignedInteger('bytes');
            $table->binary('data');
            $table->string('status', 16)->default('pending'); // pending | approved | rejected
            $table->timestamps();
            $table->index(['place_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('place_photos');
        Schema::dropIfExists('place_alerts');
        Schema::table('places', function (Blueprint $table) {
            $table->dropIndex(['status', 'category_id']);
            $table->dropIndex(['event_end_at']);
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['event_start_at', 'event_end_at']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
