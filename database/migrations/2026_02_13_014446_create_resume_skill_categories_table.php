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
        Schema::create('resume_skill_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('version_id')->constrained('resume_versions')->cascadeOnDelete();
            $table->string('group');
            $table->string('title');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resume_skill_categories');
    }
};
