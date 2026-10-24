<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Company Intel: a Company shared by every application at it, the insights
 * gathered about it, and the Role intel belonging to one application.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('website_domain')->nullable()->unique();
            $table->string('website_url', 2048)->nullable();
            $table->string('headquarters')->nullable();
            $table->string('employee_count')->nullable();
            $table->string('revenue')->nullable();
            $table->string('industry')->nullable();
            $table->unsignedSmallInteger('founded_year')->nullable();
            $table->string('ownership', 20)->nullable();
            $table->string('ticker', 20)->nullable();
            $table->text('summary')->nullable();
            $table->timestamp('researched_at')->nullable();
            $table->timestamps();

            $table->index('name');
        });

        Schema::create('company_insights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('category', 30);
            $table->string('title');
            $table->text('content');
            $table->string('source_url', 2048)->nullable();
            $table->timestamps();
        });

        Schema::create('role_intels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->unique()->constrained('applications')->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('ai_conversation_id')->nullable()->unique()->constrained('ai_conversations')->nullOnDelete();
            $table->string('status', 20)->default('queued')->index();
            $table->text('failure_reason')->nullable();
            $table->text('summary')->nullable();
            $table->text('responsibilities')->nullable();
            $table->text('requirements')->nullable();
            $table->text('team')->nullable();
            $table->text('compensation')->nullable();
            $table->text('interview_process')->nullable();
            $table->text('concerns')->nullable();
            $table->json('rejected_companies')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_intels');
        Schema::dropIfExists('company_insights');
        Schema::dropIfExists('companies');
    }
};
