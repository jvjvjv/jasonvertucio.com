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
            // Make tailored_data nullable since we want to allow applying without finalized resume
            $table->json('tailored_data')->nullable()->change();

            $table->boolean('base_resume')->default(false)->after('pdf_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('targeted_resumes', function (Blueprint $table) {
            $table->json('tailored_data')->nullable(false)->change();
            $table->dropColumn(['base_resume']);
        });
    }
};
