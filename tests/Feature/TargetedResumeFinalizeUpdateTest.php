<?php

namespace Tests\Feature;

use App\Contracts\ResumeDataServiceContract;
use App\Enums\AiConversationStatus;
use App\Enums\TargetedResumeStatus;
use App\Models\AiConversation;
use App\Models\ResumeVersion;
use App\Models\TargetedResume;
use App\Services\AiClientFactory;
use App\Services\CoverLetterDocumentService;
use App\Services\TargetedResumeDocumentService;
use App\Services\TargetedResumeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
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
        );

        $firstResume = $service->saveTailoredResume($conversation, '# Summary\nOriginal content', 72);
        $updatedResume = $service->saveTailoredResume($conversation, '# Summary\nUpdated content', 91);

        $this->assertSame($firstResume->id, $updatedResume->id);
        $this->assertSame(1, TargetedResume::query()->where('ai_conversation_id', $conversation->id)->count());
        $this->assertSame(TargetedResumeStatus::Finalized, $updatedResume->status);
        $this->assertSame(91, $updatedResume->fit_score);
        $this->assertSame('# Summary\nUpdated content', data_get($updatedResume->tailored_data, 'content'));
        $this->assertSame('# Summary\nUpdated content', data_get($updatedResume->tailored_data, 'markdown'));

        $conversation->refresh();

        $this->assertSame(AiConversationStatus::Completed, $conversation->status);
        $this->assertDatabaseHas('targeted_resumes', [
            'id' => $updatedResume->id,
            'ai_conversation_id' => $conversation->id,
            'resume_version_id' => $resumeVersion->id,
            'company_name' => 'Example Co',
            'position' => 'Senior Laravel Engineer',
            'fit_score' => 91,
            'status' => TargetedResumeStatus::Finalized->value,
        ]);

        $reloadedResume = TargetedResume::query()->findOrFail($updatedResume->id);

        $this->assertSame('# Summary\nUpdated content', data_get($reloadedResume->tailored_data, 'content'));
        $this->assertSame('# Summary\nUpdated content', data_get($reloadedResume->tailored_data, 'markdown'));
    }
}
