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
        Schema::create('targeted_resumes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resume_version_id')->constrained('resume_versions')->restrictOnDelete();
            $table->foreignId('ai_conversation_id')->nullable()->constrained('ai_conversations')->nullOnDelete();
            $table->string('company_name');
            $table->string('position');
            $table->longText('job_description');
            $table->json('tailored_data');
            $table->unsignedTinyInteger('fit_score')->nullable();
            $table->text('fit_summary')->nullable();
            $table->string('docx_path')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('status', 50)->default('draft');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('targeted_resumes');
    }
};
