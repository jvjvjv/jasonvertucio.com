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
        Schema::create('local_media', function (Blueprint $table) {
            $table->id();
            $table->string('jellyfin_item_id', 64)->unique();
            $table->string('jellyfin_user_id', 64)->nullable();
            $table->string('event_type', 32)->nullable();
            $table->string('media_type', 32)->nullable();
            $table->string('title', 255);
            $table->string('series_name', 255)->nullable();
            $table->integer('season_number')->nullable();
            $table->integer('episode_number')->nullable();
            $table->integer('year')->nullable();
            $table->json('provider_ids')->nullable();
            $table->bigInteger('playback_position')->nullable();
            $table->bigInteger('playback_duration')->nullable();
            $table->boolean('is_paused')->default(false);
            $table->timestamp('last_playback_at')->nullable();
            $table->integer('play_count')->default(0);
            $table->json('webhook_data')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('jellyfin_user_id');
            $table->index('event_type');
            $table->index('media_type');
            $table->index('last_playback_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('local_media');
    }
};
