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
        Schema::create('job_url_parsers', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->index();
            $table->string('company_name_selector')->nullable();
            $table->string('job_title_selector')->nullable();
            $table->string('job_description_selector')->nullable();
            $table->longText('html')->nullable();
            $table->text('ai_reasoning')->nullable();
            $table->string('status', 20)->default('inactive');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_url_parsers');
    }
};
