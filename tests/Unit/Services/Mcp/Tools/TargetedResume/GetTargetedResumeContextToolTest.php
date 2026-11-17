<?php

namespace Tests\Unit\Services\Mcp\Tools\TargetedResume;

use App\Models\Application;
use App\Models\CoverLetter;
use App\Models\TargetedResume;
use App\Models\User;
use App\Services\ApplicationService;
use App\Services\Mcp\Tools\TargetedResume\GetTargetedResumeContextTool;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Jvjvjv\CodeTalker\Models\AiConversation;
use Jvjvjv\CodeTalker\Services\Mcp\ToolResultConverter;
use Jvjvjv\CodeTalker\Support\ToolContext;
use Laravel\Mcp\Request;
use Tests\TestCase;

class GetTargetedResumeContextToolTest extends TestCase
{
    use DatabaseTransactions;

    private function authorizedUser(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('save-resume');

        return $user;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function handle(GetTargetedResumeContextTool $tool, array $input): array
    {
        return ToolResultConverter::toArray($tool->handle(new Request($input)));
    }

    public function test_it_loads_resume_context_by_conversation_id(): void
    {
        $user = $this->authorizedUser();
        $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
        $resume = TargetedResume::factory()->create([
            'title' => 'Acme Senior Laravel Engineer Resume',
            'tailored_data' => ['markdown' => '# Tailored Resume'],
        ]);
        $application = Application::factory()->create([
            'ai_conversation_id' => $conversation->id,
            'targeted_resume_id' => $resume->id,
            'resume_version_id' => $resume->resume_version_id,
            'company_name' => 'Acme Labs',
            'position' => 'Senior Laravel Engineer',
            'job_description' => 'Build APIs and platform services.',
            'fit_score' => 92,
            'fit_summary' => 'Strong alignment with backend leadership requirements.',
        ]);

        CoverLetter::query()->create([
            'resume_version_id' => $resume->resume_version_id,
            'application_id' => $application->id,
            'company_name' => $application->company_name,
            'position' => $application->position,
            'date' => now()->toDateString(),
            'greeting' => 'Hello Hiring Team,',
            'message_body' => 'I would like to contribute to your platform work.',
            'closing' => 'Sincerely,',
            'signature' => 'Jason',
        ]);

        $tool = new GetTargetedResumeContextTool(ToolContext::forConversation($conversation));

        $result = $this->handle($tool, ['conversation_id' => $conversation->id]);

        $this->assertSame('Acme Labs', data_get($result, 'job_description.company'));
        $this->assertSame('Senior Laravel Engineer', data_get($result, 'job_description.position'));
        $this->assertSame('# Tailored Resume', $result['tailored_resume']);
        $this->assertSame('Hello Hiring Team,', data_get($result, 'cover_letter.greeting'));
        $this->assertSame($resume->id, data_get($result, 'meta.targeted_resume_id'));
        $this->assertSame($conversation->id, data_get($result, 'meta.conversation_id'));
    }

    public function test_context_reports_the_company_ghosting_tally_and_says_the_penalty_is_applied_separately(): void
    {
        config()->set('resume.ghosted_fit_penalty', 10);
        $company = 'Ghostly '.Str::random(8);
        $user = $this->authorizedUser();
        $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
        $resume = TargetedResume::factory()->create(['tailored_data' => ['markdown' => '# Tailored']]);
        Application::factory()->create([
            'ai_conversation_id' => $conversation->id,
            'targeted_resume_id' => $resume->id,
            'company_name' => $company,
            'fit_score' => 80,
        ]);
        $tool = new GetTargetedResumeContextTool(ToolContext::forConversation($conversation));

        $clean = $this->handle($tool, ['conversation_id' => $conversation->id]);
        $this->assertSame(0, data_get($clean, 'company_ghosting.ghosted_applications'));
        $this->assertStringContainsString('no penalty applies', data_get($clean, 'company_ghosting.note'));

        $ghosted = Application::factory()->applied(now()->subDays(60))->create(['company_name' => $company]);
        app(ApplicationService::class)->markGhosted($ghosted, 30);

        $result = $this->handle($tool, ['conversation_id' => $conversation->id]);

        $this->assertSame(1, data_get($result, 'company_ghosting.ghosted_applications'));
        $this->assertSame(10, data_get($result, 'company_ghosting.penalty_per_ghosting'));
        $this->assertStringContainsString('do not apply this penalty yourself', data_get($result, 'company_ghosting.note'));
        $this->assertSame(80, data_get($result, 'meta.fit_score'));
    }

    public function test_it_returns_matches_when_company_search_is_ambiguous(): void
    {
        $user = $this->authorizedUser();
        $conversation = AiConversation::factory()->create(['user_id' => $user->id]);

        $firstResume = TargetedResume::factory()->create(['title' => 'Acme Backend Resume']);
        Application::factory()->create([
            'ai_conversation_id' => $conversation->id,
            'targeted_resume_id' => $firstResume->id,
            'company_name' => 'Acme Labs',
            'position' => 'Senior Laravel Engineer',
        ]);

        $secondConversation = AiConversation::factory()->create([
            'user_id' => $conversation->user_id,
        ]);

        $secondResume = TargetedResume::factory()->create(['title' => 'Acme Platform Resume']);
        Application::factory()->applied()->create([
            'ai_conversation_id' => $secondConversation->id,
            'targeted_resume_id' => $secondResume->id,
            'company_name' => 'Acme Labs',
            'position' => 'Staff PHP Engineer',
        ]);

        $otherUserConversation = AiConversation::factory()->create();
        Application::factory()->create([
            'ai_conversation_id' => $otherUserConversation->id,
            'targeted_resume_id' => TargetedResume::factory()->create(['title' => 'Other User Resume'])->id,
            'company_name' => 'Acme Labs',
            'position' => 'Should Not Leak',
        ]);

        $tool = new GetTargetedResumeContextTool(ToolContext::forConversation($conversation));

        $result = $this->handle($tool, ['company_name' => 'Acme']);

        $this->assertTrue($result['needs_selection']);
        $this->assertCount(2, $result['matches']);
        $this->assertSame(
            [$secondResume->id, $firstResume->id],
            array_column($result['matches'], 'targeted_resume_id'),
        );
        $this->assertSame(['applied', 'finalized'], array_column($result['matches'], 'status'));
    }

    public function test_it_finds_a_resume_by_job_title(): void
    {
        $user = $this->authorizedUser();
        $conversation = AiConversation::factory()->create(['user_id' => $user->id]);

        $resume = TargetedResume::factory()->create(['title' => 'Northwind Platform Architect Resume']);
        Application::factory()->create([
            'ai_conversation_id' => $conversation->id,
            'targeted_resume_id' => $resume->id,
            'company_name' => 'Northwind',
            'position' => 'Platform Architect',
            'job_description' => 'Lead platform modernization.',
        ]);

        $tool = new GetTargetedResumeContextTool(ToolContext::forConversation($conversation));

        $result = $this->handle($tool, ['job_title' => 'Platform Architect']);

        $this->assertSame($resume->id, data_get($result, 'meta.targeted_resume_id'));
        $this->assertSame('Northwind', data_get($result, 'job_description.company'));
    }
}
