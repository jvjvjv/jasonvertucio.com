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
        Schema::table('job_url_parsers', function (Blueprint $table) {
            $table->string('job_location_selector')->nullable()->after('job_title_selector');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_url_parsers', function (Blueprint $table) {
            $table->dropColumn('job_location_selector');
        });
    }
};
