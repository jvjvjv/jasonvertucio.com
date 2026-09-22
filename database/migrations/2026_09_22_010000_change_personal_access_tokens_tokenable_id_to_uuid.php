<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Makes Sanctum's token table usable with this application's UUID users.
 *
 * The 2019 stub migration is Sanctum's stock one, which declares
 * `morphs('tokenable')` and therefore a `bigint unsigned` key. `User` uses
 * `HasUuids` and a `char(36)` primary key, so `createToken()` has always failed
 * with "Data truncated for column 'tokenable_id'" — personal access tokens have
 * never been issuable in this application.
 *
 * Both databases hold zero rows, so the columns are dropped and re-declared
 * rather than altered in place. If that ever stops being true, this migration
 * must be rewritten to convert values instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->dropIndex('personal_access_tokens_tokenable_type_tokenable_id_index');
            $table->dropColumn(['tokenable_type', 'tokenable_id']);
        });

        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->uuidMorphs('tokenable');
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->dropIndex('personal_access_tokens_tokenable_type_tokenable_id_index');
            $table->dropColumn(['tokenable_type', 'tokenable_id']);
        });

        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->morphs('tokenable');
        });
    }
};
