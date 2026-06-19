<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The pivot tables that Keystone reads with ->withPivot('tenant_id')->withTimestamps().
     *
     * @var array<int, string>
     */
    protected array $tables = ['model_has_roles', 'model_has_permissions'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (!Schema::hasColumn($tableName, 'tenant_id')) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    $table->uuid('tenant_id')->nullable()->after('model_id');
                    $table->index('tenant_id', "{$tableName}_tenant_id_index");
                });
            }

            if (!Schema::hasColumn($tableName, 'created_at')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->timestamps();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (Schema::hasColumn($tableName, 'created_at')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropTimestamps();
                });
            }

            if (Schema::hasColumn($tableName, 'tenant_id')) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    $table->dropIndex("{$tableName}_tenant_id_index");
                    $table->dropColumn('tenant_id');
                });
            }
        }
    }
};
