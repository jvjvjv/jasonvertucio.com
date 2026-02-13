<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('resume_downloads', function (Blueprint $table) {
            $table->foreignId('version_id')
                ->nullable()
                ->after('id')
                ->constrained('resume_versions')
                ->nullOnDelete();
        });

        // Backfill version_id from existing version strings
        DB::table('resume_downloads')
            ->whereNotNull('version')
            ->where('version', '!=', '')
            ->orderBy('id')
            ->chunk(100, function ($downloads) {
                foreach ($downloads as $download) {
                    $versionRecord = DB::table('resume_versions')
                        ->where('version', $download->version)
                        ->first();

                    if ($versionRecord) {
                        DB::table('resume_downloads')
                            ->where('id', $download->id)
                            ->update(['version_id' => $versionRecord->id]);
                    }
                }
            });

        Schema::table('resume_downloads', function (Blueprint $table) {
            $table->dropIndex(['version']);
            $table->dropColumn('version');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('resume_downloads', function (Blueprint $table) {
            $table->string('version', 20)->after('id');
        });

        // Restore version strings from version_id
        DB::table('resume_downloads')
            ->whereNotNull('version_id')
            ->orderBy('id')
            ->chunk(100, function ($downloads) {
                foreach ($downloads as $download) {
                    $versionRecord = DB::table('resume_versions')
                        ->where('id', $download->version_id)
                        ->first();

                    if ($versionRecord) {
                        DB::table('resume_downloads')
                            ->where('id', $download->id)
                            ->update(['version' => $versionRecord->version]);
                    }
                }
            });

        Schema::table('resume_downloads', function (Blueprint $table) {
            $table->index('version');
            $table->dropConstrainedForeignId('version_id');
        });
    }
};
