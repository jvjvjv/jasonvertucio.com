<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $latestVersionId = DB::table('resume_versions')
            ->orderByDesc('id')
            ->value('id');

        if ($latestVersionId === null && DB::table('cover_letters')->exists()) {
            throw new RuntimeException('Cannot backfill cover letters: no resume_versions record exists.');
        }

        Schema::table('cover_letters', function (Blueprint $table) {
            $table->foreignId('resume_version_id')
                ->nullable()
                ->after('id')
                ->constrained('resume_versions')
                ->restrictOnDelete();
        });

        if ($latestVersionId !== null) {
            DB::table('cover_letters')
                ->whereNull('resume_version_id')
                ->update([
                    'resume_version_id' => $latestVersionId,
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cover_letters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('resume_version_id');
        });
    }
};
