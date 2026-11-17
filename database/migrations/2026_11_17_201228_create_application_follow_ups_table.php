<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI-drafted follow-up emails to an application's contacts. A row is
 * drafted as `pending` and is only ever sent after the admin approves it;
 * `recipients` is the snapshot of contact addresses taken at approval.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->string('status', 20);
            $table->string('subject');
            $table->text('body');
            $table->json('recipients')->nullable();
            $table->foreignId('ai_system_id')->nullable()->constrained('ai_systems')->nullOnDelete();
            $table->timestamp('drafted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('discarded_at')->nullable();
            $table->string('discard_reason', 20)->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['application_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_follow_ups');
    }
};
