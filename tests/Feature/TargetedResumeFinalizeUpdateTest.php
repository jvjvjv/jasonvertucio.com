<?php

namespace Tests\Feature;

use App\Contracts\ResumeDataServiceContract;
use Jvjvjv\CodeTalker\Enums\AiConversationStatus;
use Jvjvjv\CodeTalker\Enums\AiInteractionStatus;
use App\Enums\TargetedResumeStatus;
use Jvjvjv\CodeTalker\Models\AiConversation;
use Jvjvjv\CodeTalker\Models\AiInteractionLog;
use Jvjvjv\CodeTalker\Models\AiSystem;
use App\Models\ResumeVersion;
use App\Models\TargetedResume;
use Jvjvjv\CodeTalker\Services\AiClientFactory;
use Jvjvjv\CodeTalker\Services\AiMemoryService;
use Jvjvjv\CodeTalker\Services\ClaudeService;
use Jvjvjv\CodeTalker\Services\ConversationUsageService;
use App\Services\CoverLetterDocumentService;
use App\Services\TargetedResumeDocumentService;
use App\Services\TargetedResumeService;
use Generator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\TestCase;

class TargetedResumeFinalizeUpdateTest extends TestCase
{
    use DatabaseTransactions;

    public function testSaveTailoredResumeUpdatesExistingFinalizedResumeForConversation(): void
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
            $this->createMock(AiClientFactory::class),
            $this->createMock(ResumeDataServiceContract::class),
            $documentService,
            $this->createMock(CoverLetterDocumentService::class),
            $this->createMock(AiMemoryService::class),
            new ConversationUsageService(),
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

    public function testSaveTailoredResumeFallsBackToSummaryForTitleWhenExplicitTitleIsMissing(): void {
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
            $this->createMock(AiClientFactory::class),
            $this->createMock(ResumeDataServiceContract::class),
            $documentService,
            $this->createMock(CoverLetterDocumentService::class),
            $this->createMock(AiMemoryService::class),
            new ConversationUsageService(),
        );

        $resume = $service->saveTailoredResume(
            $conversation,
            "# Summary\nSenior Frontend Engineer with 12 years of experience leading product UI teams.\n\n# Skills\n## Frontend\nReact, TypeScript",
            88,
        );

        $this->assertSame('Senior Frontend Engineer', $resume->title);
        $this->assertSame('Senior Frontend Engineer', data_get($resume->tailored_data, 'title'));
    }

    public function testContinueConversationSyncsUsageAfterSuccessfulResponse(): void {
        $system = AiSystem::factory()->create(['model' => 'claude-sonnet-4-6']);
        $resumeVersion = ResumeVersion::factory()->create();

        $client = Mockery::mock(ClaudeService::class);
        $client->shouldReceive('withSystem')->once()->andReturnSelf();
        $client->shouldReceive('withMaxTokens')->once()->andReturnSelf();
        $client->shouldReceive('withTools')->once()->andReturnSelf();
        $client->shouldReceive('stream')->once()->andReturn($this->usageAwareStream());
        $client->shouldReceive('formatAssistantToolCallTurn')->never();
        $client->shouldReceive('formatToolResultTurn')->never();

        $clientFactory = Mockery::mock(AiClientFactory::class);
        $clientFactory->shouldReceive('forSystem')->once()->andReturn($client);

        $service = new TargetedResumeService(
            $clientFactory,
            $this->createMock(ResumeDataServiceContract::class),
            $this->createMock(TargetedResumeDocumentService::class),
            $this->createMock(CoverLetterDocumentService::class),
            $this->createMock(AiMemoryService::class),
            new ConversationUsageService(),
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

    private function usageAwareStream(): Generator {
        yield [
            'type' => 'message_start',
            'message' => [
                'usage' => ['input_tokens' => 1200],
            ],
        ];
        yield [
            'type' => 'content_block_delta',
            'delta' => ['text' => 'Targeted resume analysis complete.'],
        ];
        yield [
            'type' => 'message_delta',
            'usage' => ['output_tokens' => 300],
        ];
        yield ['type' => 'message_stop'];
    }

    protected function tearDown(): void {
        Mockery::close();

        parent::tearDown();
    }
}
