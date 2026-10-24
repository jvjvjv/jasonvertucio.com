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
        Schema::dropIfExists('papers');
    }

    /**
     * Reverse the migrations.
     *
     * Restores structure only — the 621 rows this table held are archived at
     * storage/app/archives/papers-2026-09-08.sql (not in version control) and
     * must be restored from that file; rolling back this migration alone
     * recreates an empty table.
     */
    public function down(): void
    {
        Schema::create('papers', function (Blueprint $table) {
            $table->id();
            $table->string('edition_id')->unique();
            $table->text('edition');
            $table->timestamp('published_at');
            $table->timestamps();
        });
    }
};
