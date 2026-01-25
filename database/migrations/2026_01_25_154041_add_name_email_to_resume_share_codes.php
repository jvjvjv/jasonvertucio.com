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
        Schema::table('resume_share_codes', function (Blueprint $table) {
            $table->string('name', 255)->default('')->after('id');
            $table->string('email', 255)->nullable()->after('name');
            $table->boolean('email_sent')->default(false)->after('expires_at');
            $table->boolean('notify_on_update')->default(false)->after('email_sent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('resume_share_codes', function (Blueprint $table) {
            $table->dropColumn('name');
            $table->dropColumn('email');
            $table->dropColumn('email_sent');
            $table->dropColumn('notify_on_update');
        });
    }
};
