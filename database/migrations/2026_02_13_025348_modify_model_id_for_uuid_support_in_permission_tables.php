<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Spatie Permission creates model_id as bigint unsigned by default.
     * Since the User model uses UUIDs (char 36), we need to widen
     * the column to VARCHAR(36) to prevent data truncation.
     */
    public function up(): void
    {
        $tables = ['model_has_roles', 'model_has_permissions'];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                $column = DB::selectOne("SHOW COLUMNS FROM {$table} WHERE Field = 'model_id'");
                if ($column && ! str_contains($column->Type, 'varchar')) {
                    DB::statement("ALTER TABLE {$table} MODIFY model_id VARCHAR(36) NOT NULL");
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = ['model_has_roles', 'model_has_permissions'];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                DB::statement("ALTER TABLE {$table} MODIFY model_id BIGINT UNSIGNED NOT NULL");
            }
        }
    }
};
