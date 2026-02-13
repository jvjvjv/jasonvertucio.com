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
        Schema::create('resume_experiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('version_id')->constrained('resume_versions')->cascadeOnDelete();
            $table->string('job_title');
            $table->string('company');
            $table->string('location')->nullable();
            $table->string('date_start')->nullable();
            $table->string('date_end')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resume_experiences');
    }
};
