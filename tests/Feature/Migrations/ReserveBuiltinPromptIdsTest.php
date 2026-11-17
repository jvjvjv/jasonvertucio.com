<?php

namespace Tests\Feature\Migrations;

use App\Services\ApplicationFollowUpService;
use App\Services\CompanyIntel\CompanyIntelService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Jvjvjv\CodeTalker\Models\AiSystem;
use Jvjvjv\CodeTalker\Models\AiSystemPrompt;
use RuntimeException;
use Tests\TestCase;

/**
 * Exercises the prompt-id reservation and the Company Intel prompt seed.
 *
 * Only the data move runs here: the reservation's AUTO_INCREMENT change is an
 * ALTER TABLE, which commits implicitly and would break the test transaction.
 * The migration has already run against the test database, so the
 * auto-increment it left behind is asserted directly.
 */
class ReserveBuiltinPromptIdsTest extends TestCase
{
    use DatabaseTransactions;

    private function reservation(): object
    {
        return require database_path('migrations/2026_10_24_115900_reserve_builtin_ai_system_prompt_ids.php');
    }

    private function stagePrompt(int $id, string $title): void
    {
        DB::table('ai_system_prompts')->insert([
            'id' => $id,
            'title' => $title,
            'description' => "{$title} description",
            'content' => "{$title} content",
            'created_at' => now()->subYear(),
            'updated_at' => now()->subYear(),
        ]);
    }

    public function test_admin_prompts_in_the_reserved_range_move_to_fifty_or_above(): void
    {
        // The real run happens before the Company Intel and Application
        // Follow-up prompts are seeded.
        DB::table('ai_system_prompts')->whereIn('id', [
            CompanyIntelService::PROMPT_ID_COMPANY_INTEL,
            ApplicationFollowUpService::PROMPT_ID_FOLLOW_UP,
        ])->delete();
        $builtins = DB::table('ai_system_prompts')->whereBetween('id', [1, 5])->get()->keyBy('id');

        $this->stagePrompt(7, 'Mock Interviewer');
        $this->stagePrompt(8, 'Bonsai');
        $system = AiSystem::factory()->create(['system_prompt_id' => 8]);

        $moved = $this->reservation()->moveAdminPrompts();

        $this->assertSame([7, 8], array_keys($moved));
        $this->assertGreaterThanOrEqual(50, $moved[7]);
        $this->assertSame($moved[7] + 1, $moved[8]);

        $this->assertNull(AiSystemPrompt::find(7));
        $this->assertNull(AiSystemPrompt::find(8));

        $interviewer = AiSystemPrompt::findOrFail($moved[7]);
        $this->assertSame('Mock Interviewer', $interviewer->title);
        $this->assertSame('Mock Interviewer content', $interviewer->content);
        $this->assertSame('Mock Interviewer description', $interviewer->description);

        $this->assertSame($moved[8], $system->fresh()->system_prompt_id);

        foreach ($builtins as $id => $builtin) {
            $this->assertSame($builtin->title, AiSystemPrompt::findOrFail($id)->title);
        }
    }

    public function test_a_new_prompt_receives_an_id_of_fifty_or_more(): void
    {
        $prompt = AiSystemPrompt::create(['title' => 'Fresh', 'description' => 'Fresh', 'content' => 'x']);

        $this->assertGreaterThanOrEqual(50, $prompt->id);
    }

    public function test_the_company_intel_prompt_is_seeded_at_its_fixed_id(): void
    {
        $prompt = AiSystemPrompt::findOrFail(CompanyIntelService::PROMPT_ID_COMPANY_INTEL);

        $this->assertNotSame('', trim($prompt->content));
    }

    public function test_the_seed_refuses_to_overwrite_a_taken_id(): void
    {
        DB::table('ai_system_prompts')
            ->where('id', CompanyIntelService::PROMPT_ID_COMPANY_INTEL)
            ->update(['title' => 'Somebody Else']);

        $seed = require database_path('migrations/2026_10_24_115901_seed_company_intel_prompt.php');

        try {
            $seed->up();
            $this->fail('The seed overwrote an existing prompt.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Somebody Else', $e->getMessage());
        }

        $this->assertSame('Somebody Else', AiSystemPrompt::findOrFail(CompanyIntelService::PROMPT_ID_COMPANY_INTEL)->title);
    }

    public function test_the_application_follow_up_prompt_is_seeded_at_its_fixed_id(): void
    {
        $prompt = AiSystemPrompt::findOrFail(ApplicationFollowUpService::PROMPT_ID_FOLLOW_UP);

        $this->assertSame(7, ApplicationFollowUpService::PROMPT_ID_FOLLOW_UP);
        $this->assertSame('Application Follow-up Prompt', $prompt->title);
        $this->assertSame(trim(ApplicationFollowUpService::buildFollowUpPrompt()), trim($prompt->content));
    }

    public function test_the_follow_up_seed_refuses_to_overwrite_a_taken_id(): void
    {
        DB::table('ai_system_prompts')
            ->where('id', ApplicationFollowUpService::PROMPT_ID_FOLLOW_UP)
            ->update(['title' => 'Somebody Else', 'content' => 'Theirs']);

        $seed = require database_path('migrations/2026_11_17_201303_seed_application_follow_up_prompt.php');

        try {
            $seed->up();
            $this->fail('The seed overwrote an existing prompt.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Somebody Else', $e->getMessage());
        }

        $prompt = AiSystemPrompt::findOrFail(ApplicationFollowUpService::PROMPT_ID_FOLLOW_UP);
        $this->assertSame('Somebody Else', $prompt->title);
        $this->assertSame('Theirs', $prompt->content);
    }

    public function test_the_follow_up_seed_down_removes_only_its_row(): void
    {
        $seed = require database_path('migrations/2026_11_17_201303_seed_application_follow_up_prompt.php');
        $others = AiSystemPrompt::query()->whereKeyNot(ApplicationFollowUpService::PROMPT_ID_FOLLOW_UP)->count();

        $seed->down();

        $this->assertNull(AiSystemPrompt::find(ApplicationFollowUpService::PROMPT_ID_FOLLOW_UP));
        $this->assertSame($others, AiSystemPrompt::query()->count());

        $seed->up();

        $this->assertNotNull(AiSystemPrompt::find(ApplicationFollowUpService::PROMPT_ID_FOLLOW_UP));
    }
}
