<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('targeted_resume_status_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('targeted_resume_id')->constrained('targeted_resumes')->cascadeOnDelete();
            $table->string('status', 50);
            $table->text('notes')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });

        // Migrate existing applied_at data into the new history table
        DB::statement("
            INSERT INTO targeted_resume_status_updates (targeted_resume_id, status, occurred_at, created_at, updated_at)
            SELECT id, 'applied', applied_at, NOW(), NOW()
            FROM targeted_resumes
            WHERE applied_at IS NOT NULL
        ");

        Schema::table('targeted_resumes', function (Blueprint $table) {
            $table->dropColumn('applied_at');
        });
    }

    public function down(): void
    {
        Schema::table('targeted_resumes', function (Blueprint $table) {
            $table->timestamp('applied_at')->nullable()->after('status');
        });

        // Restore applied_at from the earliest 'applied' status update per resume
        DB::statement("
            UPDATE targeted_resumes tr
            JOIN (
                SELECT targeted_resume_id, MIN(occurred_at) AS applied_at
                FROM targeted_resume_status_updates
                WHERE status = 'applied'
                GROUP BY targeted_resume_id
            ) su ON su.targeted_resume_id = tr.id
            SET tr.applied_at = su.applied_at
        ");

        Schema::dropIfExists('targeted_resume_status_updates');
    }
};
