<?php

namespace Tests\Unit\Services\Mcp\Tools\TargetedResume;

use App\Enums\TargetedResumeStatus;
use App\Models\CoverLetter;
use App\Models\TargetedResume;
use App\Models\User;
use App\Services\Mcp\Tools\TargetedResume\GetTargetedResumeContextTool;
use Illuminate\Foundation\Testing\DatabaseTransactions;
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
     * @param array<string, mixed> $input
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
        $resume = TargetedResume::factory()->finalized()->create([
            'ai_conversation_id' => $conversation->id,
            'company_name' => 'Acme Labs',
            'position' => 'Senior Laravel Engineer',
            'title' => 'Acme Senior Laravel Engineer Resume',
            'job_description' => 'Build APIs and platform services.',
            'tailored_data' => ['markdown' => '# Tailored Resume'],
            'fit_score' => 92,
            'fit_summary' => 'Strong alignment with backend leadership requirements.',
        ]);

        CoverLetter::query()->create([
            'resume_version_id' => $resume->resume_version_id,
            'targeted_resume_id' => $resume->id,
            'company_name' => $resume->company_name,
            'position' => $resume->position,
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

    public function test_it_returns_matches_when_company_search_is_ambiguous(): void
    {
        $user = $this->authorizedUser();
        $conversation = AiConversation::factory()->create(['user_id' => $user->id]);

        $firstResume = TargetedResume::factory()->finalized()->create([
            'ai_conversation_id' => $conversation->id,
            'company_name' => 'Acme Labs',
            'position' => 'Senior Laravel Engineer',
            'title' => 'Acme Backend Resume',
            'status' => TargetedResumeStatus::Finalized,
        ]);

        $secondConversation = AiConversation::factory()->create([
            'user_id' => $conversation->user_id,
        ]);

        $secondResume = TargetedResume::factory()->applied()->create([
            'ai_conversation_id' => $secondConversation->id,
            'company_name' => 'Acme Labs',
            'position' => 'Staff PHP Engineer',
            'title' => 'Acme Platform Resume',
            'status' => TargetedResumeStatus::Applied,
        ]);

        $otherUserConversation = AiConversation::factory()->create();
        TargetedResume::factory()->finalized()->create([
            'ai_conversation_id' => $otherUserConversation->id,
            'company_name' => 'Acme Labs',
            'position' => 'Should Not Leak',
            'title' => 'Other User Resume',
        ]);

        $tool = new GetTargetedResumeContextTool(ToolContext::forConversation($conversation));

        $result = $this->handle($tool, ['company_name' => 'Acme']);

        $this->assertTrue($result['needs_selection']);
        $this->assertCount(2, $result['matches']);
        $this->assertSame(
            [$secondResume->id, $firstResume->id],
            array_column($result['matches'], 'targeted_resume_id'),
        );
    }

    public function test_it_finds_a_resume_by_job_title(): void
    {
        $user = $this->authorizedUser();
        $conversation = AiConversation::factory()->create(['user_id' => $user->id]);

        $resume = TargetedResume::factory()->finalized()->create([
            'ai_conversation_id' => $conversation->id,
            'company_name' => 'Northwind',
            'position' => 'Platform Architect',
            'title' => 'Northwind Platform Architect Resume',
            'job_description' => 'Lead platform modernization.',
        ]);

        $tool = new GetTargetedResumeContextTool(ToolContext::forConversation($conversation));

        $result = $this->handle($tool, ['job_title' => 'Platform Architect']);

        $this->assertSame($resume->id, data_get($result, 'meta.targeted_resume_id'));
        $this->assertSame('Northwind', data_get($result, 'job_description.company'));
    }
}
