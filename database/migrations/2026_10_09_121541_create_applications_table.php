<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema only. The tracked job moves off `targeted_resumes` onto its own
 * `applications` table; the old columns stay in place until
 * `strip_targeted_resumes`, so this step and the backfill after it are both
 * non-destructive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resume_version_id')->constrained('resume_versions')->restrictOnDelete();
            $table->foreignId('targeted_resume_id')->nullable()->unique()->constrained('targeted_resumes')->nullOnDelete();
            $table->foreignId('ai_conversation_id')->nullable()->unique()->constrained('ai_conversations')->nullOnDelete();
            $table->foreignUuid('job_url_id')->nullable()->constrained('job_urls')->nullOnDelete();
            $table->string('company_name');
            $table->string('position');
            $table->string('location')->nullable();
            $table->longText('job_description');
            $table->unsignedTinyInteger('fit_score')->nullable();
            $table->text('fit_summary')->nullable();
            $table->string('status', 50)->default('draft')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('application_status_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->string('status', 50);
            $table->text('notes')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });

        Schema::table('cover_letters', function (Blueprint $table) {
            $table->foreignId('application_id')->nullable()->after('targeted_resume_id')->constrained('applications')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cover_letters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('application_id');
        });

        Schema::dropIfExists('application_status_updates');
        Schema::dropIfExists('applications');
    }
};
