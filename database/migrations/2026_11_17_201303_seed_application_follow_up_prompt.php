<?php

use App\Services\ApplicationFollowUpService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the Application Follow-up prompt at its fixed built-in id, after the
 * Company Intel prompt. Refuses to overwrite whatever already holds that id.
 */
return new class extends Migration
{
    public function up(): void
    {
        $existing = DB::table('ai_system_prompts')->where('id', ApplicationFollowUpService::PROMPT_ID_FOLLOW_UP)->first();

        if ($existing !== null) {
            throw new RuntimeException(sprintf(
                'System prompt id %d is already taken by "%s"; the Application Follow-up prompt was not seeded.',
                ApplicationFollowUpService::PROMPT_ID_FOLLOW_UP,
                $existing->title,
            ));
        }

        DB::table('ai_system_prompts')->insert([
            'id' => ApplicationFollowUpService::PROMPT_ID_FOLLOW_UP,
            'title' => 'Application Follow-up Prompt',
            'description' => 'A system prompt for drafting follow-up emails to the contacts on a job application.',
            'content' => ApplicationFollowUpService::buildFollowUpPrompt(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('ai_system_prompts')->where('id', ApplicationFollowUpService::PROMPT_ID_FOLLOW_UP)->delete();
    }
};
