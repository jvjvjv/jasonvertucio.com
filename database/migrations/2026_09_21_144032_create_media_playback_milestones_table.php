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
        Schema::create('media_playback_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('local_media_id')->constrained('local_media')->cascadeOnDelete();
            $table->string('title', 255);
            $table->string('media_type', 32)->nullable();
            $table->timestamp('finished_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_playback_milestones');
    }
};
