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
        Schema::create('resume_edit_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('base_resume_version_id')->constrained('resume_versions')->restrictOnDelete();
            $table->unsignedInteger('revision_number');
            $table->string('status', 20)->default('pending');
            $table->json('snapshot');
            $table->foreignId('ai_conversation_id')->nullable()->constrained('ai_conversations')->nullOnDelete();
            $table->timestamp('batch_started_at');
            $table->timestamp('last_edited_at');
            $table->timestamp('approved_at')->nullable();
            $table->foreignUuid('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['base_resume_version_id', 'revision_number'], 'resume_edit_candidates_base_version_revision_unique');
            $table->index(['base_resume_version_id', 'status'], 'resume_edit_candidates_base_version_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resume_edit_candidates');
    }
};
