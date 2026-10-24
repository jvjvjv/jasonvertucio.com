<?php

use App\Services\CompanyIntel\CompanyIntelService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the Company Intel prompt at its fixed built-in id, beside the prompts
 * seeded by `seed_default_ai_system_prompts`. Refuses to overwrite whatever
 * already holds that id.
 */
return new class extends Migration
{
    public function up(): void
    {
        $existing = DB::table('ai_system_prompts')->where('id', CompanyIntelService::PROMPT_ID_COMPANY_INTEL)->first();

        if ($existing !== null) {
            throw new RuntimeException(sprintf(
                'System prompt id %d is already taken by "%s"; the Company Intel prompt was not seeded.',
                CompanyIntelService::PROMPT_ID_COMPANY_INTEL,
                $existing->title,
            ));
        }

        DB::table('ai_system_prompts')->insert([
            'id' => CompanyIntelService::PROMPT_ID_COMPANY_INTEL,
            'title' => 'Company Intel Prompt',
            'description' => 'A system prompt for researching the company and role behind a job application.',
            'content' => CompanyIntelService::buildCompanyIntelPrompt(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('ai_system_prompts')->where('id', CompanyIntelService::PROMPT_ID_COMPANY_INTEL)->delete();
    }
};
