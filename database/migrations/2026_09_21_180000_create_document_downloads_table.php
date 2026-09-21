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
        Schema::create('document_downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resume_id')->nullable()->constrained('resume_versions')->nullOnDelete();
            $table->foreignId('targeted_resume_id')->nullable()->constrained('targeted_resumes')->nullOnDelete();
            $table->foreignId('cover_letter_id')->nullable()->constrained('cover_letters')->nullOnDelete();
            $table->string('type', 4);
            $table->boolean('served_cached_document')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_downloads');
    }
};
