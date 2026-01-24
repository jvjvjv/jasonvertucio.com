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
        Schema::create('resume_downloads', function (Blueprint $table) {
            $table->id();
            $table->string('version', 20);
            $table->string('ip_address', 45);
            $table->string('user_agent', 512)->nullable();
            $table->string('share_code_id', 6)->nullable();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index('version');
            $table->index('share_code_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resume_downloads');
    }
};
