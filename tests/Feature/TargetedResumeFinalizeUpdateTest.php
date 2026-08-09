<?php

namespace Tests\Feature;

use App\Contracts\ResumeDataServiceContract;
use App\Enums\TargetedResumeStatus;
use App\Models\ResumeVersion;
use App\Models\TargetedResume;
use App\Services\CoverLetterDocumentService;
use App\Services\TargetedResumeDocumentService;
use App\Services\TargetedResumeService;
use Generator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Jvjvjv\CodeTalker\Enums\AiConversationStatus;
use Jvjvjv\CodeTalker\Enums\AiInteractionStatus;
use Jvjvjv\CodeTalker\Models\AiConversation;
use Jvjvjv\CodeTalker\Models\AiInteractionLog;
use Jvjvjv\CodeTalker\Models\AiSystem;
use Jvjvjv\CodeTalker\Services\AiMemoryService;
use Jvjvjv\CodeTalker\Services\ConversationUsageService;
use Jvjvjv\CodeTalker\Services\LaravelAi\AgentFactory;
use Jvjvjv\CodeTalker\Services\LaravelAi\CodeTalkerAgent;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Streaming\Events\StreamEnd;
use Laravel\Ai\Streaming\Events\StreamStart;
use Laravel\Ai\Streaming\Events\TextDelta;
use Mockery;
use Tests\TestCase;

class TargetedResumeFinalizeUpdateTest extends TestCase
{
    use DatabaseTransactions;

    public function test_save_tailored_resume_updates_existing_finalized_resume_for_conversation(): void
    {
        $resumeVersion = ResumeVersion::factory()->create();
        $conversation = AiConversation::factory()->create([
            'status' => AiConversationStatus::Active,
            'context' => [
                'resume_version_id' => $resumeVersion->id,
                'company_name' => 'Example Co',
                'job_title' => 'Senior Laravel Engineer',
                'job_description' => 'Build and maintain Laravel applications.',
                'fit_summary' => 'Strong backend alignment.',
            ],
        ]);

        $documentService = $this->createMock(TargetedResumeDocumentService::class);
        $documentService->expects($this->exactly(2))
            ->method('generateDocx')
            ->willReturn(['success' => true]);
        $documentService->expects($this->exactly(2))
            ->method('generatePdf')
            ->willReturn(['success' => true]);

        $service = new TargetedResumeService(
            $this->createMock(AgentFactory::class),
            $this->createMock(ResumeDataServiceContract::class),
            $documentService,
            $this->createMock(CoverLetterDocumentService::class),
            $this->createMock(AiMemoryService::class),
            new ConversationUsageService,
        );

        $firstResume = $service->saveTailoredResume($conversation, "Title: Full Stack Engineer\n\n# Summary\nOriginal content", 72);
        $updatedResume = $service->saveTailoredResume($conversation, "Title: Senior Frontend Engineer\n\n# Summary\nUpdated content", 91);

        $this->assertSame($firstResume->id, $updatedResume->id);
        $this->assertSame(1, TargetedResume::query()->where('ai_conversation_id', $conversation->id)->count());
        $this->assertSame(TargetedResumeStatus::Finalized, $updatedResume->status);
        $this->assertSame('Senior Frontend Engineer', $updatedResume->title);
        $this->assertSame(91, $updatedResume->fit_score);
        $this->assertSame('Senior Frontend Engineer', data_get($updatedResume->tailored_data, 'title'));
        $this->assertSame("# Summary\nUpdated content", data_get($updatedResume->tailored_data, 'content'));
        $this->assertSame("# Summary\nUpdated content", data_get($updatedResume->tailored_data, 'markdown'));

        $conversation->refresh();

        $this->assertSame(AiConversationStatus::Completed, $conversation->status);
        $this->assertDatabaseHas('targeted_resumes', [
            'id' => $updatedResume->id,
            'ai_conversation_id' => $conversation->id,
            'resume_version_id' => $resumeVersion->id,
            'company_name' => 'Example Co',
            'position' => 'Senior Laravel Engineer',
            'title' => 'Senior Frontend Engineer',
            'fit_score' => 91,
            'status' => TargetedResumeStatus::Finalized->value,
        ]);

        $reloadedResume = TargetedResume::query()->findOrFail($updatedResume->id);

        $this->assertSame("# Summary\nUpdated content", data_get($reloadedResume->tailored_data, 'content'));
        $this->assertSame("# Summary\nUpdated content", data_get($reloadedResume->tailored_data, 'markdown'));
    }

    public function test_save_tailored_resume_falls_back_to_summary_for_title_when_explicit_title_is_missing(): void
    {
        $resumeVersion = ResumeVersion::factory()->create();
        $conversation = AiConversation::factory()->create([
            'status' => AiConversationStatus::Active,
            'context' => [
                'resume_version_id' => $resumeVersion->id,
                'company_name' => 'Example Co',
                'job_title' => 'Senior Laravel Engineer',
                'job_description' => 'Build and maintain Laravel applications.',
            ],
        ]);

        $documentService = $this->createMock(TargetedResumeDocumentService::class);
        $documentService->expects($this->once())
            ->method('generateDocx')
            ->willReturn(['success' => true]);
        $documentService->expects($this->once())
            ->method('generatePdf')
            ->willReturn(['success' => true]);

        $service = new TargetedResumeService(
            $this->createMock(AgentFactory::class),
            $this->createMock(ResumeDataServiceContract::class),
            $documentService,
            $this->createMock(CoverLetterDocumentService::class),
            $this->createMock(AiMemoryService::class),
            new ConversationUsageService,
        );

        $resume = $service->saveTailoredResume(
            $conversation,
            "# Summary\nSenior Frontend Engineer with 12 years of experience leading product UI teams.\n\n# Skills\n## Frontend\nReact, TypeScript",
            88,
        );

        $this->assertSame('Senior Frontend Engineer', $resume->title);
        $this->assertSame('Senior Frontend Engineer', data_get($resume->tailored_data, 'title'));
    }

    public function test_continue_conversation_syncs_usage_after_successful_response(): void
    {
        $system = AiSystem::factory()->create(['model' => 'claude-sonnet-4-6']);
        $resumeVersion = ResumeVersion::factory()->create();

        $agent = Mockery::mock(CodeTalkerAgent::class);
        $agent->shouldReceive('messages')->andReturn([]);
        $agent->shouldReceive('stream')->once()->andReturn($this->usageAwareStream());
        $agent->shouldReceive('append')->never();

        $agentFactory = Mockery::mock(AgentFactory::class);
        $agentFactory->shouldReceive('forSystem')->once()->andReturn($agent);

        $service = new TargetedResumeService(
            $agentFactory,
            $this->createMock(ResumeDataServiceContract::class),
            $this->createMock(TargetedResumeDocumentService::class),
            $this->createMock(CoverLetterDocumentService::class),
            $this->createMock(AiMemoryService::class),
            new ConversationUsageService,
        );

        $conversation = $service->startConversation(
            $system,
            'Build and maintain Laravel applications.',
            $resumeVersion,
            'Senior Laravel Engineer',
            'Example Co',
        );

        iterator_to_array($service->continueConversation($conversation));

        $conversation->refresh();

        $this->assertSame(1200, $conversation->usage_input_tokens);
        $this->assertSame(300, $conversation->usage_output_tokens);
        $this->assertSame(1500, $conversation->usage_total_tokens);
        $this->assertSame('0.008100', (string) $conversation->usage_cost_usd);
        $this->assertNotNull($conversation->usage_synced_at);

        $this->assertDatabaseHas('ai_interaction_logs', [
            'ai_conversation_id' => $conversation->id,
            'feature' => 'targeted-resume',
            'status' => AiInteractionStatus::Success->value,
            'input_tokens' => 1200,
            'output_tokens' => 300,
        ]);
    }

    private function usageAwareStream(): StreamableAgentResponse
    {
        return new StreamableAgentResponse(
            'id-1',
            static function (): Generator {
                yield new StreamStart('id-1', 'anthropic', 'claude-sonnet-4-6', time());
                yield new TextDelta('e1', 'm1', 'Targeted resume analysis complete.', time());
                yield new StreamEnd(
                    'id-1',
                    'stop',
                    new Usage(promptTokens: 1200, completionTokens: 300),
                    time(),
                );
            },
            new Meta(provider: 'anthropic', model: 'claude-sonnet-4-6'),
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
