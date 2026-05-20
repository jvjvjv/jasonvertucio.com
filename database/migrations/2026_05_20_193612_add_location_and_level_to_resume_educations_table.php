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
        Schema::table('resume_educations', function (Blueprint $table) {
            $table->string('location')->nullable()->after('institution');
            $table->string('level')->nullable()->after('degree');
        });
    }

    public function down(): void
    {
        Schema::table('resume_educations', function (Blueprint $table) {
            $table->dropColumn(['location', 'level']);
        });
    }
};
