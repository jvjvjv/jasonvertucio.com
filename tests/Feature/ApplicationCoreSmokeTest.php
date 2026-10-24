<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Exceptions\ApplicationAlreadyHasConversationException;
use App\Exceptions\ApplicationInPipelineException;
use App\Exceptions\ApplicationStatusUpdateMismatchException;
use App\Exceptions\TargetedResumeAlreadySentException;
use App\Exceptions\TerminalApplicationException;
use App\Models\AiConversation;
use App\Models\Application;
use App\Models\ApplicationStatusUpdate;
use App\Models\CoverLetter;
use App\Models\ResumeVersion;
use App\Models\TargetedResume;
use App\Models\User;
use App\Services\ApplicationMetricsService;
use App\Services\ApplicationService;
use App\Services\CoverLetterDocumentService;
use App\Services\Mcp\Tools\TargetedResume\GetJobDescriptionTool;
use App\Services\Mcp\Tools\TargetedResume\UpdateFitAssessmentTool;
use App\Services\Mcp\Tools\TargetedResume\UpdateStatusTool;
use App\Services\TargetedResumeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Jvjvjv\CodeTalker\Enums\AiConversationStatus;
use Jvjvjv\CodeTalker\Models\AiConversation as BaseAiConversation;
use Jvjvjv\CodeTalker\Models\AiConversationMessage;
use Jvjvjv\CodeTalker\Models\AiSystem;
use Jvjvjv\CodeTalker\Services\Mcp\ToolResultConverter;
use Jvjvjv\CodeTalker\Support\ToolContext;
use Laravel\Mcp\Request;
use Tests\TestCase;

/**
 * Wiring checks for the application-tracking core: relations, factory states
 * and the service entry points. The spec scenarios have their own suites.
 */
class ApplicationCoreSmokeTest extends TestCase
{
    use DatabaseTransactions;

    private function service(): ApplicationService
    {
        return app(ApplicationService::class);
    }

    public function test_default_factory_state_is_a_draft_main_resume_application(): void
    {
        $application = Application::factory()->create();

        $this->assertSame(ApplicationStatus::Draft, $application->status);
        $this->assertNull($application->targeted_resume_id);
        $this->assertNull($application->ai_conversation_id);
        $this->assertInstanceOf(ResumeVersion::class, $application->resumeVersion);
    }

    public function test_factory_states_attach_a_resume_and_a_session(): void
    {
        $application = Application::factory()->withTargetedResume()->withConversation()->applied()->create();

        $this->assertInstanceOf(TargetedResume::class, $application->targetedResume);
        $this->assertNotNull($application->targetedResume->tailored_data);
        $this->assertSame($application->resume_version_id, $application->targetedResume->resume_version_id);
        $this->assertSame('targeted-resume', $application->conversation->feature);
        $this->assertSame(ApplicationStatus::Applied, $application->latestStatusUpdate->status);
        $this->assertTrue($application->targetedResume->application->is($application));
    }

    public function test_conversation_reaches_its_document_through_the_application(): void
    {
        $application = Application::factory()->withTargetedResume()->withConversation()->create();
        Application::factory()->withTargetedResume()->withConversation()->create();

        $conversation = AiConversation::query()->findOrFail($application->ai_conversation_id);

        $this->assertTrue($conversation->application->is($application));
        $this->assertSame($application->targeted_resume_id, $conversation->targetedResume->id);
        $this->assertInstanceOf(TargetedResume::class, $conversation->targetedResume);

        $eagerLoaded = AiConversation::query()->with('targetedResume')->findOrFail($application->ai_conversation_id);
        $this->assertSame($application->targeted_resume_id, $eagerLoaded->targetedResume->id);

        $withoutDocument = Application::factory()->withConversation()->create();
        $this->assertNull(AiConversation::query()->findOrFail($withoutDocument->ai_conversation_id)->targetedResume);
    }

    public function test_filenames_fall_back_to_the_application_id_without_a_session(): void
    {
        $application = Application::factory()->withTargetedResume()->create(['company_name' => 'Acme Corp']);
        $letter = CoverLetter::create([
            'resume_version_id' => $application->resume_version_id,
            'application_id' => $application->id,
            'company_name' => 'Acme Corp',
            'position' => 'Engineer',
            'date' => '2026-03-01',
            'greeting' => 'Hello,',
            'message_body' => 'Body.',
        ]);

        $this->assertStringEndsWith("Resume Acme Corp app-{$application->id}", $application->targetedResume->generateFilename());
        $this->assertStringEndsWith("Cover Letter Acme Corp 2026-03-01 app-{$application->id}", $letter->generateFilename());

        $withSession = Application::factory()->withTargetedResume()->withConversation()->create();
        $this->assertStringEndsWith($withSession->conversation->uuid, $withSession->targetedResume->generateFilename());
    }

    public function test_create_for_analysis_creates_the_application_and_its_session_together(): void
    {
        $this->actingAs(User::factory()->create());
        ResumeVersion::query()->update(['is_current' => false]);
        $current = ResumeVersion::factory()->create(['is_current' => true]);

        $application = $this->service()->createForAnalysis(
            AiSystem::factory()->create(),
            'Build things.',
            'Acme Corp',
            'Staff Engineer',
            'Philadelphia, PA',
        );

        $this->assertSame(ApplicationStatus::Draft, $application->status);
        $this->assertSame($current->id, $application->resume_version_id);
        $this->assertSame('Philadelphia, PA', $application->location);
        $this->assertSame(['step' => 'analysis', 'auto_start_pending' => true], $application->conversation->context);
        $this->assertSame(0, $application->statusUpdates()->count());

        $firstMessage = $application->conversation->messages()->where('role', 'user')->first();
        $this->assertStringContainsString('Job Title: Staff Engineer', $firstMessage->content);
        $this->assertStringContainsString('Job Location: Philadelphia, PA', $firstMessage->content);
        $this->assertStringContainsString('Company Name: Acme Corp', $firstMessage->content);
    }

    public function test_create_applied_records_the_resume_version_and_a_first_entry(): void
    {
        $version = ResumeVersion::factory()->create();

        $application = $this->service()->createApplied('Build things.', 'Acme Corp', 'Engineer', resumeVersionId: $version->id);

        $this->assertSame(ApplicationStatus::Applied, $application->fresh()->status);
        $this->assertSame($version->id, $application->resume_version_id);
        $this->assertNull($application->ai_conversation_id);
        $this->assertNull($application->targeted_resume_id);
        $this->assertSame(1, $application->statusUpdates()->where('status', 'applied')->count());
    }

    public function test_begin_analysis_keeps_status_and_refuses_a_second_session(): void
    {
        $this->actingAs(User::factory()->create());
        $application = Application::factory()->applied()->create();
        $system = AiSystem::factory()->create();

        $this->service()->beginAnalysis($application, $system);

        $this->assertNotNull($application->fresh()->ai_conversation_id);
        $this->assertSame(ApplicationStatus::Applied, $application->fresh()->status);
        $this->assertSame(1, $application->statusUpdates()->count());

        $this->expectException(ApplicationAlreadyHasConversationException::class);
        $this->service()->beginAnalysis($application->fresh(), $system);
    }

    public function test_mark_applied_uses_the_targeted_resume_or_records_the_chosen_version(): void
    {
        $chosen = ResumeVersion::factory()->create();

        $targeted = Application::factory()->withTargetedResume()->create();
        $originalVersionId = $targeted->resume_version_id;
        $this->service()->markApplied($targeted, $chosen->id);

        $this->assertSame($originalVersionId, $targeted->fresh()->resume_version_id);
        $this->assertSame(ApplicationStatus::Applied, $targeted->fresh()->status);

        $main = Application::factory()->passed()->withConversation()->create();
        $documents = TargetedResume::query()->count();
        $this->service()->markApplied($main, $chosen->id, Carbon::parse('2026-02-03 09:00:00'));

        $this->assertSame($chosen->id, $main->fresh()->resume_version_id);
        $this->assertSame(ApplicationStatus::Applied, $main->fresh()->status);
        $this->assertNull($main->fresh()->targeted_resume_id);
        $this->assertSame($documents, TargetedResume::query()->count());
        $this->assertSame('2026-02-03', $main->latestStatusUpdate->occurred_at->toDateString());
    }

    public function test_pass_marks_the_application_and_its_session(): void
    {
        $application = Application::factory()->withConversation()->create();

        $this->service()->pass($application);

        $this->assertSame(ApplicationStatus::Passed, $application->fresh()->status);
        $this->assertSame(AiConversationStatus::Pass, $application->conversation->fresh()->status);

        $this->expectException(ApplicationInPipelineException::class);
        $this->service()->pass(Application::factory()->applied()->create());
    }

    public function test_status_history_drives_the_status(): void
    {
        $application = Application::factory()->applied(now()->subDays(10))->create();
        $service = $this->service();

        $interview = $service->addStatusUpdate($application, ApplicationStatus::Interviewing, 'Round one', now()->subDays(5));
        $this->assertSame(ApplicationStatus::Interviewing, $application->fresh()->status);

        $service->updateStatusUpdate($application, $interview, 'Round one, moved', now()->subDays(4));
        $this->assertSame('Round one, moved', $interview->fresh()->notes);
        $this->assertSame(ApplicationStatus::Interviewing, $application->fresh()->status);

        $service->deleteStatusUpdate($application, $interview);
        $this->assertSame(ApplicationStatus::Applied, $application->fresh()->status);

        $service->deleteStatusUpdate($application, $application->statusUpdates()->firstOrFail());
        $this->assertSame(ApplicationStatus::Draft, $application->fresh()->status);
    }

    public function test_status_history_refusals(): void
    {
        $service = $this->service();
        $rejected = Application::factory()->create(['status' => ApplicationStatus::Rejected]);

        try {
            $service->addStatusUpdate($rejected, ApplicationStatus::Interviewing);
            $this->fail('A terminal application accepted a status update.');
        } catch (TerminalApplicationException) {
            $this->assertSame(0, $rejected->statusUpdates()->count());
        }

        $foreign = ApplicationStatusUpdate::factory()->create();

        $this->expectException(ApplicationStatusUpdateMismatchException::class);
        $service->deleteStatusUpdate(Application::factory()->create(), $foreign);
    }

    public function test_update_details_writes_the_job_to_the_application_and_flags_to_the_session(): void
    {
        $application = Application::factory()->withConversation()->create();

        $this->service()->updateDetails($application, [
            'title' => 'My chat',
            'company_name' => 'Northwind',
            'position' => '',
            'location' => 'Remote',
            'fit_score' => 77,
        ]);

        $application->refresh();
        $context = $application->conversation->context;

        $this->assertSame('Northwind', $application->company_name);
        $this->assertNotSame('', $application->position);
        $this->assertSame('Remote', $application->location);
        $this->assertSame(77, $application->fit_score);
        $this->assertSame('My chat', $application->conversation->title);
        $this->assertTrue($context['company_name_manual']);
        $this->assertTrue($context['title_manual']);
        $this->assertArrayNotHasKey('job_title_manual', $context);
        $this->assertArrayNotHasKey('company_name', $context);
    }

    public function test_delete_soft_deletes_the_application_and_its_session(): void
    {
        $application = Application::factory()->withConversation()->create();
        $conversationId = $application->ai_conversation_id;

        $this->service()->delete($application);

        $this->assertSoftDeleted('applications', ['id' => $application->id]);
        $this->assertNotNull(BaseAiConversation::withTrashed()->findOrFail($conversationId)->deleted_at);
    }

    public function test_discard_removes_the_document_and_tells_the_session(): void
    {
        $docxPath = tempnam(sys_get_temp_dir(), 'docx-').'.docx';
        file_put_contents($docxPath, 'content');

        $application = Application::factory()->withTargetedResume()->withConversation()->create();
        $resume = $application->targetedResume;
        $resume->forceFill(['docx_path' => $docxPath])->save();

        $this->service()->discardTargetedResume($resume);

        $application->refresh();
        $this->assertNull($application->targeted_resume_id);
        $this->assertSame(ApplicationStatus::Draft, $application->status);
        $this->assertNull(TargetedResume::query()->find($resume->id));
        $this->assertFileDoesNotExist($docxPath);

        $note = AiConversationMessage::query()->where('ai_conversation_id', $application->ai_conversation_id)->latest('id')->firstOrFail();
        $this->assertSame('resume_discarded', data_get($note->metadata, 'origin'));
        $this->assertSame('user', $note->role);
    }

    public function test_discard_is_refused_once_applied_and_silent_without_a_session(): void
    {
        $withoutSession = Application::factory()->withTargetedResume()->create();
        $messages = AiConversationMessage::query()->count();
        $this->service()->discardTargetedResume($withoutSession);
        $this->assertNull($withoutSession->fresh()->targeted_resume_id);
        $this->assertSame($messages, AiConversationMessage::query()->count());

        $applied = Application::factory()->withTargetedResume()->applied()->create();

        try {
            $this->service()->discardTargetedResume($applied);
            $this->fail('An applied application let its targeted resume be discarded.');
        } catch (TargetedResumeAlreadySentException) {
            $this->assertNotNull($applied->fresh()->targeted_resume_id);
            $this->assertNotNull(TargetedResume::query()->find($applied->targeted_resume_id));
        }
    }

    public function test_cover_letter_saves_without_a_targeted_resume_and_is_replaced(): void
    {
        $documents = $this->createStub(CoverLetterDocumentService::class);
        $documents->method('generateDocx')->willReturn(['success' => true]);
        $documents->method('generatePdf')->willReturn(['success' => true]);
        $this->app->instance(CoverLetterDocumentService::class, $documents);

        $application = Application::factory()->withConversation()->create(['company_name' => 'Acme Corp']);
        $service = app(TargetedResumeService::class);

        $first = $service->saveCoverLetter($application, "Hello team,\nFirst body.");
        $second = $service->saveCoverLetter($application, "Hello team,\nSecond body.");

        $this->assertSame($first->id, $second->id);
        $this->assertSame($application->id, $second->application_id);
        $this->assertSame($application->resume_version_id, $second->resume_version_id);
        $this->assertSame('Acme Corp', $second->company_name);
        $this->assertSame('Second body.', $second->message_body);
        $this->assertSame(1, $application->coverLetters()->count());
    }

    public function test_tools_read_and_write_the_application(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('save-resume');
        $application = Application::factory()->create([
            'ai_conversation_id' => BaseAiConversation::factory()->create(['user_id' => $user->id])->id,
            'company_name' => Application::UNKNOWN_COMPANY,
            'position' => 'Engineer',
            'job_description' => 'Build things.',
        ]);
        $context = ToolContext::forConversation(BaseAiConversation::query()->findOrFail($application->ai_conversation_id));

        $job = ToolResultConverter::toArray((new GetJobDescriptionTool($context))->handle(new Request([])));
        $this->assertSame(['job_description' => 'Build things.', 'job_title' => 'Engineer', 'company_name' => null], $job);

        $fit = ToolResultConverter::toArray((new UpdateFitAssessmentTool($context))->handle(new Request([
            'fit_score' => 81,
            'fit_summary' => 'Good fit.',
            'company_name' => 'Acme Corp',
        ])));
        $this->assertSame(['fit_score', 'fit_summary', 'company_name'], $fit['updated']);
        $this->assertSame('Acme Corp', $application->fresh()->company_name);
        $this->assertSame(81, $application->fresh()->fit_score);
        $this->assertSame([], $application->conversation->fresh()->context);

        $status = ToolResultConverter::toArray(app()->makeWith(UpdateStatusTool::class, ['context' => $context])->handle(new Request([
            'status' => 'applied',
            'occurred_at' => '2026-06-12',
        ])));
        $this->assertTrue($status['success']);
        $this->assertSame('2026-06-12', $status['occurred_at']);
        $this->assertSame(ApplicationStatus::Applied, $application->fresh()->status);
        $this->assertSame('2026-06-12 12:00:00', $application->latestStatusUpdate->occurred_at->toDateTimeString());
    }

    public function test_metrics_filter_by_applied_date_inclusive_of_both_ends(): void
    {
        config()->set('resume.ghosted_after_days', 30);
        $metrics = app(ApplicationMetricsService::class);

        $march = [Carbon::parse('2026-03-01'), Carbon::parse('2026-03-31')];
        $all = $metrics->build()['kpis']['totalApplied'];
        $inMarch = $metrics->build(...$march)['kpis']['totalApplied'];
        $fromApril = $metrics->build(Carbon::parse('2026-04-01'))['kpis']['totalApplied'];
        $untilMarch = $metrics->build(null, Carbon::parse('2026-03-31'))['kpis']['totalApplied'];

        Application::factory()->applied(Carbon::parse('2026-03-01 00:00:00'))->create();
        Application::factory()->applied(Carbon::parse('2026-03-31 23:59:00'))->create();
        Application::factory()->applied(Carbon::parse('2026-04-01 00:00:00'))->create();
        Application::factory()->applied(Carbon::parse('2026-03-15 10:00:00'))->create()->delete();
        Application::factory()->create();

        $this->assertSame($all + 3, $metrics->build()['kpis']['totalApplied']);

        $filtered = $metrics->build(...$march);
        $this->assertSame($inMarch + 2, $filtered['kpis']['totalApplied']);
        $this->assertCount($inMarch + 2, $filtered['timeline']);
        $this->assertSame($inMarch + 2, $filtered['funnel'][0]['count']);

        $this->assertSame($fromApril + 1, $metrics->build(Carbon::parse('2026-04-01'))['kpis']['totalApplied']);
        $this->assertSame($untilMarch + 2, $metrics->build(null, Carbon::parse('2026-03-31'))['kpis']['totalApplied']);
    }
}
