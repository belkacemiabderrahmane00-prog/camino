<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Balade à plusieurs : un groupe éphémère (lien / QR), ses membres avec leur position, ses messages et photos.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('walks')) {
            Schema::create('walks', function (Blueprint $table) {
                $table->id();
                $table->string('code', 12)->unique();
                $table->string('title', 160);
                $table->foreignId('itinerary_id')->nullable()->constrained()->nullOnDelete();
                $table->json('route_json')->nullable();
                $table->unsignedBigInteger('host_member_id')->nullable();
                $table->decimal('meeting_lat', 10, 7)->nullable();
                $table->decimal('meeting_lng', 10, 7)->nullable();
                $table->string('meeting_label', 160)->nullable();
                $table->unsignedBigInteger('meeting_by')->nullable();
                $table->timestamp('meeting_at')->nullable();
                $table->string('status', 12)->default('active');
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('walk_members')) {
            Schema::create('walk_members', function (Blueprint $table) {
                $table->id();
                $table->foreignId('walk_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('token', 48)->unique();
                $table->string('name', 40);
                $table->string('color', 9)->default('#0F8B8D');
                $table->decimal('lat', 10, 7)->nullable();
                $table->decimal('lng', 10, 7)->nullable();
                $table->float('heading')->nullable();
                $table->float('accuracy')->nullable();
                $table->timestamp('seen_at')->nullable();
                $table->timestamp('arrived_at')->nullable();
                $table->timestamp('left_at')->nullable();
                $table->timestamps();
                $table->index(['walk_id', 'seen_at']);
            });
        }
        if (! Schema::hasTable('walk_messages')) {
            Schema::create('walk_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('walk_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->nullable()->constrained('walk_members')->nullOnDelete();
                $table->string('type', 12)->default('text');
                $table->text('body')->nullable();
                $table->binary('photo')->nullable();
                $table->string('photo_mime', 40)->nullable();
                $table->unsignedInteger('photo_bytes')->nullable();
                $table->decimal('lat', 10, 7)->nullable();
                $table->decimal('lng', 10, 7)->nullable();
                $table->timestamps();
                $table->index(['walk_id', 'id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('walk_messages');
        Schema::dropIfExists('walk_members');
        Schema::dropIfExists('walks');
    }
};
