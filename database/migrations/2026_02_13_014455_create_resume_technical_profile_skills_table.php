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
        Schema::create('resume_technical_profile_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_category_id')->constrained('resume_technical_profile_categories')->cascadeOnDelete();
            $table->string('skill');
            $table->decimal('years', 4, 1)->nullable();
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resume_technical_profile_skills');
    }
};
