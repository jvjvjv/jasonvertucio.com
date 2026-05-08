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
        Schema::table('targeted_resumes', function (Blueprint $table) {
            $table->foreignUuid('job_url_id')->nullable()->after('ai_conversation_id')
                ->constrained('job_urls')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('targeted_resumes', function (Blueprint $table) {
            $table->dropForeign(['job_url_id']);
            $table->dropColumn('job_url_id');
        });
    }
};
