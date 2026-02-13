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
        Schema::create('resume_technical_profile_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('version_id')->constrained('resume_versions')->cascadeOnDelete();
            $table->string('category');
            $table->boolean('is_main')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resume_technical_profile_categories');
    }
};
