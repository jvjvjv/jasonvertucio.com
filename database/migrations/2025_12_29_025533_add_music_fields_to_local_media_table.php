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
        Schema::table('local_media', function (Blueprint $table) {
            $table->string('artist_name', 255)->nullable()->after('series_name');
            $table->string('album_name', 255)->nullable()->after('artist_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('local_media', function (Blueprint $table) {
            $table->dropColumn(['artist_name', 'album_name']);
        });
    }
};
