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
        Schema::create('job_urls', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('job_url_parser_id')->constrained('job_url_parsers')->cascadeOnDelete();
            $table->string('url');
            $table->text('contents');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_urls');
    }
};
