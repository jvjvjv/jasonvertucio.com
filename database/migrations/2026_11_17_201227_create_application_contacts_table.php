<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The people involved in an application's hiring process. The email is
 * lower-cased on save, so the unique index enforces "unique within the
 * application, ignoring case".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->string('email', 254);
            $table->string('name')->nullable();
            $table->string('role')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['application_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_contacts');
    }
};
