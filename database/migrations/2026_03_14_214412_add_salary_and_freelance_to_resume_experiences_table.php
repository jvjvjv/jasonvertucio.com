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
        Schema::table('resume_experiences', function (Blueprint $table) {
            $table->decimal('salary_start_amount', 10, 2)->nullable()->after('date_end');
            $table->string('salary_start_period')->nullable()->after('salary_start_amount');
            $table->decimal('salary_end_amount', 10, 2)->nullable()->after('salary_start_period');
            $table->string('salary_end_period')->nullable()->after('salary_end_amount');
            $table->boolean('is_freelance')->default(false)->after('salary_end_period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('resume_experiences', function (Blueprint $table) {
            $table->dropColumn([
                'salary_start_amount',
                'salary_start_period',
                'salary_end_amount',
                'salary_end_period',
                'is_freelance',
            ]);
        });
    }
};
