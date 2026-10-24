<?php

namespace Tests\Feature\Services;

use App\Enums\ApplicationStatus;
use App\Exceptions\ApplicationAlreadyHasConversationException;
use App\Exceptions\ApplicationException;
use App\Exceptions\ApplicationInPipelineException;
use App\Exceptions\ApplicationStatusUpdateMismatchException;
use App\Exceptions\NonPipelineStatusException;
use App\Exceptions\ResumeVersionUnavailableException;
use App\Exceptions\TargetedResumeAlreadySentException;
use App\Exceptions\TargetedResumeMissingException;
use App\Exceptions\TerminalApplicationException;
use App\Models\AiConversation;
use App\Models\Application;
use App\Models\ApplicationStatusUpdate;
use App\Models\CoverLetter;
use App\Models\ResumeVersion;
use App\Models\TargetedResume;
use App\Models\User;
use App\Services\ApplicationService;
use App\Services\TargetedResumeService;
use App\Support\ApplicationStatusResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Jvjvjv\CodeTalker\Enums\AiConversationStatus;
use Jvjvjv\CodeTalker\Models\AiConversationMessage;
use Jvjvjv\CodeTalker\Models\AiInteractionLog;
use Jvjvjv\CodeTalker\Models\AiLlmMessage;
use Jvjvjv\CodeTalker\Models\AiSystem;
use Jvjvjv\CodeTalker\Services\LaravelAi\AgentFactory;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * One test per scenario of the `application-tracking` spec that can be
 * exercised at service level. The agent factory is replaced by a mock that
 * refuses to build an agent, so no test here can reach a model.
 */
class ApplicationServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const string JOB_DESCRIPTION = 'Build and maintain Laravel applications.';

    private const array JOB_CONTEXT_KEYS = [
        'job_title',
        'company_name',
        'job_description',
        'job_location',
        'location',
        'position',
        'job_url_id',
        'resume_version_id',
        'fit_score',
        'fit_summary',
    ];

    /**
     * @var array<int, string>
     */
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(AgentFactory::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('forSystem');
        });

        $this->actingAs(User::factory()->create());
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (file_exists($path)) {
                @unlink($path);
            }
        }

        $this->travelBack();

        parent::tearDown();
    }

    // ---------------------------------------------------------------------
    // Requirement: An Application records a tracked job
    // ---------------------------------------------------------------------

    public function test_application_without_a_targeted_resume(): void
    {
        $version = $this->currentResumeVersion();

        $application = $this->service()->createApplied(self::JOB_DESCRIPTION, 'Acme Corp', 'Staff Engineer', 'Philadelphia, PA');

        $stored = Application::query()->findOrFail($application->id);

        $this->assertSame($version->id, $stored->resume_version_id);
        $this->assertTrue($stored->resumeVersion->is($version));
        $this->assertNull($stored->targeted_resume_id);
        $this->assertNull($stored->targetedResume);
        $this->assertNull($stored->ai_conversation_id);
        $this->assertNull($stored->conversation);
        $this->assertSame('Acme Corp', $stored->company_name);
        $this->assertSame('Staff Engineer', $stored->position);
        $this->assertSame('Philadelphia, PA', $stored->location);
        $this->assertSame(self::JOB_DESCRIPTION, $stored->job_description);
    }

    public function test_application_with_a_targeted_resume(): void
    {
        $application = Application::factory()->withConversation()->create([
            'company_name' => 'Acme Corp',
            'position' => 'Staff Engineer',
            'location' => 'Remote',
            'job_description' => self::JOB_DESCRIPTION,
            'fit_score' => 64,
            'fit_summary' => 'Strong backend alignment.',
        ]);

        $targetedResume = $this->finalize($application, "Title: Staff Platform Engineer\n\n# Summary\nTailored content");

        $stored = Application::query()->findOrFail($application->id);

        $this->assertSame($targetedResume->id, $stored->targeted_resume_id);
        $this->assertTrue($stored->targetedResume->is($targetedResume));
        $this->assertTrue($targetedResume->application->is($stored));

        $this->assertSame('Acme Corp', $stored->company_name);
        $this->assertSame('Staff Engineer', $stored->position);
        $this->assertSame('Remote', $stored->location);
        $this->assertSame(self::JOB_DESCRIPTION, $stored->job_description);
        $this->assertSame(64, $stored->fit_score);
        $this->assertSame('Strong backend alignment.', $stored->fit_summary);
        $this->assertSame(ApplicationStatus::Draft, $stored->status);

        $documentColumns = array_keys(TargetedResume::query()->findOrFail($targetedResume->id)->getAttributes());

        foreach (['company_name', 'position', 'job_description', 'job_url_id', 'fit_score', 'fit_summary', 'status', 'base_resume'] as $jobColumn) {
            $this->assertNotContains($jobColumn, $documentColumns, "targeted_resumes still carries {$jobColumn}");
        }
    }

    // ---------------------------------------------------------------------
    // Requirement: Starting a new session (service level)
    // ---------------------------------------------------------------------

    public function test_begin_analysis_creates_a_draft_application_with_a_session_attached(): void
    {
        $version = $this->currentResumeVersion();
        $system = AiSystem::factory()->create();
        $applications = Application::query()->count();

        $application = $this->service()->createForAnalysis($system, self::JOB_DESCRIPTION, 'Acme Corp', 'Staff Engineer', 'Philadelphia, PA');

        $stored = Application::query()->findOrFail($application->id);

        $this->assertSame($applications + 1, Application::query()->count());
        $this->assertSame(ApplicationStatus::Draft, $stored->status);
        $this->assertSame($version->id, $stored->resume_version_id);
        $this->assertNull($stored->targeted_resume_id);
        $this->assertSame(0, $stored->statusUpdates()->count());

        $this->assertNotNull($stored->ai_conversation_id);
        $this->assertInstanceOf(AiConversation::class, $stored->conversation);
        $this->assertSame('targeted-resume', $stored->conversation->feature);
        $this->assertSame($system->id, $stored->conversation->ai_system_id);
        $this->assertSame(AiConversationStatus::Active, $stored->conversation->status);
        $this->assertTrue($stored->conversation->application->is($stored));
    }

    public function test_begin_analysis_session_context_holds_only_step_and_auto_start_pending(): void
    {
        $this->currentResumeVersion();

        $application = $this->service()->createForAnalysis(
            AiSystem::factory()->create(),
            self::JOB_DESCRIPTION,
            'Acme Corp',
            'Staff Engineer',
            'Philadelphia, PA',
        );

        $context = AiConversation::query()->findOrFail($application->ai_conversation_id)->context;
        $keys = array_keys($context);
        sort($keys);

        $this->assertSame(['auto_start_pending', 'step'], $keys);
        $this->assertTrue($context['auto_start_pending']);

        foreach (self::JOB_CONTEXT_KEYS as $jobKey) {
            $this->assertArrayNotHasKey($jobKey, $context);
        }
    }

    public function test_begin_analysis_queues_the_first_analysis_message_from_the_application(): void
    {
        $this->currentResumeVersion();

        $application = $this->service()->createForAnalysis(
            AiSystem::factory()->create(),
            self::JOB_DESCRIPTION,
            'Acme Corp',
            'Staff Engineer',
            'Philadelphia, PA',
        );

        $messages = AiConversationMessage::query()->where('ai_conversation_id', $application->ai_conversation_id)->orderBy('id')->get();

        $this->assertSame(['system', 'user'], $messages->pluck('role')->all());
        $this->assertStringContainsString(self::JOB_DESCRIPTION, $messages[1]->content);
        $this->assertStringContainsString('Staff Engineer', $messages[1]->content);
        $this->assertStringContainsString('Acme Corp', $messages[1]->content);
        $this->assertStringContainsString('Philadelphia, PA', $messages[1]->content);
        $this->assertSame(0, AiLlmMessage::query()->where('ai_conversation_id', $application->ai_conversation_id)->count());
    }

    public function test_begin_analysis_is_refused_without_a_current_resume_version_and_leaves_nothing_behind(): void
    {
        ResumeVersion::query()->update(['is_current' => false]);
        $applications = Application::withTrashed()->count();
        $conversations = AiConversation::withTrashed()->count();

        try {
            $this->service()->createForAnalysis(AiSystem::factory()->create(), self::JOB_DESCRIPTION, 'Acme Corp', 'Engineer');
            $this->fail('An application was created without a resume version.');
        } catch (ResumeVersionUnavailableException $exception) {
            $this->assertInstanceOf(ApplicationException::class, $exception);
        }

        $this->assertSame($applications, Application::withTrashed()->count());
        $this->assertSame($conversations, AiConversation::withTrashed()->count());
    }

    public function test_i_applied_creates_an_applied_application_with_the_current_resume_version_and_no_session(): void
    {
        $this->travelTo(Carbon::parse('2026-05-04 14:30:00'));
        $current = $this->currentResumeVersion();
        ResumeVersion::factory()->create();
        $conversations = AiConversation::withTrashed()->count();
        $documents = TargetedResume::query()->count();

        $application = $this->service()->createApplied(self::JOB_DESCRIPTION, 'Acme Corp', 'Staff Engineer');

        $stored = Application::query()->findOrFail($application->id);

        $this->assertSame($current->id, $stored->resume_version_id);
        $this->assertNull($stored->ai_conversation_id);
        $this->assertNull($stored->targeted_resume_id);
        $this->assertSame(ApplicationStatus::Applied, $stored->status);
        $this->assertSame(ApplicationStatus::Applied, $application->status);
        $this->assertSame($conversations, AiConversation::withTrashed()->count());
        $this->assertSame($documents, TargetedResume::query()->count());

        $entries = $stored->statusUpdates()->get();

        $this->assertCount(1, $entries);
        $this->assertSame(ApplicationStatus::Applied, $entries[0]->status);
        $this->assertSame('2026-05-04 14:30:00', $entries[0]->occurred_at->toDateTimeString());
    }

    public function test_i_applied_records_the_chosen_resume_version(): void
    {
        $this->currentResumeVersion();
        $chosen = ResumeVersion::factory()->create();

        $application = $this->service()->createApplied(self::JOB_DESCRIPTION, 'Acme Corp', 'Engineer', resumeVersionId: $chosen->id);

        $this->assertSame($chosen->id, $application->fresh()->resume_version_id);
        $this->assertSame(ApplicationStatus::Applied, $application->fresh()->status);
    }

    public function test_i_applied_dates_the_entry_with_an_explicit_date(): void
    {
        $this->travelTo(Carbon::parse('2026-05-04 14:30:00'));
        $this->currentResumeVersion();

        $application = $this->service()->createApplied(
            self::JOB_DESCRIPTION,
            'Acme Corp',
            'Engineer',
            occurredAt: Carbon::parse('2026-04-20 09:15:00'),
        );

        $entry = $application->statusUpdates()->sole();

        $this->assertSame(ApplicationStatus::Applied, $entry->status);
        $this->assertSame('2026-04-20 09:15:00', $entry->occurred_at->toDateTimeString());
    }

    public function test_i_applied_with_an_unknown_resume_version_is_refused_and_leaves_nothing_behind(): void
    {
        $this->currentResumeVersion();
        $unknownId = (int) ResumeVersion::query()->max('id') + 1000;
        $applications = Application::withTrashed()->count();
        $entries = ApplicationStatusUpdate::query()->count();

        try {
            $this->service()->createApplied(self::JOB_DESCRIPTION, 'Acme Corp', 'Engineer', resumeVersionId: $unknownId);
            $this->fail('An application was created against a resume version that does not exist.');
        } catch (ResumeVersionUnavailableException) {
            $this->assertSame($applications, Application::withTrashed()->count());
            $this->assertSame($entries, ApplicationStatusUpdate::query()->count());
        }
    }

    public function test_i_applied_without_any_resume_version_is_refused(): void
    {
        ResumeVersion::query()->update(['is_current' => false]);
        $applications = Application::withTrashed()->count();

        try {
            $this->service()->createApplied(self::JOB_DESCRIPTION, 'Acme Corp', 'Engineer');
            $this->fail('An application was created without a resume version.');
        } catch (ResumeVersionUnavailableException) {
            $this->assertSame($applications, Application::withTrashed()->count());
        }
    }

    public function test_blank_company_and_position_are_stored_as_the_unknown_placeholders(): void
    {
        $this->currentResumeVersion();

        $applied = $this->service()->createApplied(self::JOB_DESCRIPTION, '  ', null, '   ')->fresh();
        $analysed = $this->service()->createForAnalysis(AiSystem::factory()->create(), self::JOB_DESCRIPTION, null, '')->fresh();

        foreach ([$applied, $analysed] as $application) {
            $this->assertSame(Application::UNKNOWN_COMPANY, $application->company_name);
            $this->assertSame(Application::UNKNOWN_POSITION, $application->position);
            $this->assertNull($application->location);
            $this->assertNull($application->knownCompanyName());
            $this->assertNull($application->knownPosition());
        }

        $firstMessage = AiConversationMessage::query()
            ->where('ai_conversation_id', $analysed->ai_conversation_id)
            ->where('role', 'user')
            ->sole();

        $this->assertStringNotContainsString(Application::UNKNOWN_COMPANY, $firstMessage->content);
        $this->assertStringNotContainsString(Application::UNKNOWN_POSITION, $firstMessage->content);
    }

    // ---------------------------------------------------------------------
    // Requirement: Application statuses
    // ---------------------------------------------------------------------

    public function test_passing_on_a_job(): void
    {
        $application = Application::factory()->create();

        $this->service()->pass($application);

        $stored = $application->fresh();

        $this->assertSame(ApplicationStatus::Passed, $stored->status);
        $this->assertSame(0, $stored->statusUpdates()->count());
    }

    public function test_passing_on_a_job_marks_its_session_passed_too(): void
    {
        $application = Application::factory()->withConversation()->create();

        $this->service()->pass($application);

        $this->assertSame(ApplicationStatus::Passed, $application->fresh()->status);
        $this->assertSame(AiConversationStatus::Pass, AiConversation::query()->findOrFail($application->ai_conversation_id)->status);
    }

    /**
     * @return array<string, array{0: ApplicationStatus}>
     */
    public static function pipelineStatusProvider(): array
    {
        return collect(ApplicationStatus::pipeline())
            ->mapWithKeys(fn (ApplicationStatus $status): array => [$status->value => [$status]])
            ->all();
    }

    #[DataProvider('pipelineStatusProvider')]
    public function test_passing_is_refused_once_the_application_is_in_the_pipeline(ApplicationStatus $status): void
    {
        $application = Application::factory()->applied()->withConversation()->create(['status' => $status]);

        try {
            $this->service()->pass($application);
            $this->fail("A {$status->value} application was passed on.");
        } catch (ApplicationInPipelineException $exception) {
            $this->assertInstanceOf(ApplicationException::class, $exception);
        }

        $this->assertSame($status, $application->fresh()->status);
        $this->assertSame(1, $application->statusUpdates()->count());
        $this->assertSame(AiConversationStatus::Active, AiConversation::query()->findOrFail($application->ai_conversation_id)->status);
    }

    public function test_applying_after_passing(): void
    {
        $this->travelTo(Carbon::parse('2026-05-04 14:30:00'));
        $application = Application::factory()->passed()->create();

        $this->service()->markApplied($application, $application->resume_version_id);

        $stored = $application->fresh();
        $entries = $stored->statusUpdates()->get();

        $this->assertSame(ApplicationStatus::Applied, $stored->status);
        $this->assertCount(1, $entries);
        $this->assertSame(ApplicationStatus::Applied, $entries[0]->status);
        $this->assertSame('2026-05-04 14:30:00', $entries[0]->occurred_at->toDateTimeString());
    }

    public function test_ghosted_display_once_the_latest_entry_is_older_than_the_threshold(): void
    {
        $this->travelTo(Carbon::parse('2026-06-15 12:00:00'));
        $threshold = (int) config('resume.ghosted_after_days');
        $this->assertGreaterThan(0, $threshold);

        $application = Application::factory()->applied(now()->subDays($threshold)->subSecond())->create()->fresh();
        $latest = $application->latestStatusUpdate;

        $this->assertSame(
            ApplicationStatusResolver::GHOSTED,
            ApplicationStatusResolver::resolve($application->status->value, $latest->occurred_at),
        );
        $this->assertSame('ghosted', ApplicationStatusResolver::GHOSTED);
        $this->assertSame(ApplicationStatus::Applied, $application->fresh()->status);
        $this->assertSame('applied', $application->fresh()->getRawOriginal('status'));
        $this->assertSame(ApplicationStatus::Applied, $latest->fresh()->status);
    }

    public function test_ghosted_display_does_not_apply_inside_the_threshold(): void
    {
        $this->travelTo(Carbon::parse('2026-06-15 12:00:00'));
        $threshold = (int) config('resume.ghosted_after_days');

        $justInside = Application::factory()->applied(now()->subDays($threshold)->addSecond())->create()->fresh();
        $onTheThreshold = Application::factory()->applied(now()->subDays($threshold))->create()->fresh();

        $this->assertSame('applied', ApplicationStatusResolver::resolve($justInside->status->value, $justInside->latestStatusUpdate->occurred_at));
        $this->assertSame('applied', ApplicationStatusResolver::resolve($onTheThreshold->status->value, $onTheThreshold->latestStatusUpdate->occurred_at));
    }

    public function test_ghosted_display_is_judged_by_the_latest_entry_and_only_for_applied(): void
    {
        $this->travelTo(Carbon::parse('2026-06-15 12:00:00'));
        $threshold = (int) config('resume.ghosted_after_days');
        $longAgo = now()->subDays($threshold * 3);

        $reapplied = Application::factory()->applied($longAgo)->create();
        $this->service()->addStatusUpdate($reapplied, ApplicationStatus::Applied, 'Followed up', now()->subDay());
        $reapplied = $reapplied->fresh();

        $this->assertSame('applied', ApplicationStatusResolver::resolve($reapplied->status->value, $reapplied->latestStatusUpdate->occurred_at));

        foreach ([ApplicationStatus::Draft, ApplicationStatus::Passed, ApplicationStatus::Interviewing, ApplicationStatus::Offered, ApplicationStatus::Rejected] as $status) {
            $this->assertSame($status->value, ApplicationStatusResolver::resolve($status->value, $longAgo));
        }

        $this->assertSame('draft', ApplicationStatusResolver::resolve('draft', null));
    }

    public function test_having_a_targeted_resume_is_not_a_status(): void
    {
        $draft = Application::factory()->withConversation()->create();
        $passed = Application::factory()->passed()->withConversation()->create();

        $this->finalize($draft, "Title: Engineer\n\n# Summary\nContent");
        $this->finalize($passed, "Title: Engineer\n\n# Summary\nContent");

        $this->assertNotNull($draft->fresh()->targeted_resume_id);
        $this->assertSame('draft', $draft->fresh()->getRawOriginal('status'));
        $this->assertNotNull($passed->fresh()->targeted_resume_id);
        $this->assertSame('passed', $passed->fresh()->getRawOriginal('status'));
        $this->assertNotContains('finalized', array_column(ApplicationStatus::cases(), 'value'));
        $this->assertSame(ApplicationStatus::Draft, Application::factory()->withTargetedResume()->create()->fresh()->status);
    }

    // ---------------------------------------------------------------------
    // Requirement: Marking an application applied confirms the resume used
    // ---------------------------------------------------------------------

    public function test_applied_with_the_targeted_resume(): void
    {
        $application = Application::factory()->withTargetedResume()->withConversation()->create();
        $targetedResumeId = $application->targeted_resume_id;
        $recordedVersionId = $application->resume_version_id;
        $otherVersion = ResumeVersion::factory()->create();

        $this->service()->markApplied($application, $otherVersion->id);

        $stored = $application->fresh();

        $this->assertSame(ApplicationStatus::Applied, $stored->status);
        $this->assertSame($targetedResumeId, $stored->targeted_resume_id);
        $this->assertNotNull(TargetedResume::query()->find($targetedResumeId));
        $this->assertSame($recordedVersionId, $stored->resume_version_id);
        $this->assertSame($recordedVersionId, $stored->targetedResume->resume_version_id);
        $this->assertSame([ApplicationStatus::Applied], $stored->statusUpdates->pluck('status')->all());
    }

    public function test_applied_with_the_main_resume_after_an_analysis(): void
    {
        $application = Application::factory()->withConversation()->create();
        $chosen = ResumeVersion::factory()->create();
        $documents = TargetedResume::query()->count();

        $this->service()->markApplied($application, $chosen->id);

        $stored = $application->fresh();

        $this->assertSame(ApplicationStatus::Applied, $stored->status);
        $this->assertSame($chosen->id, $stored->resume_version_id);
        $this->assertNull($stored->targeted_resume_id);
        $this->assertSame($documents, TargetedResume::query()->count());
        $this->assertSame([ApplicationStatus::Applied], $stored->statusUpdates->pluck('status')->all());
        $this->assertNotNull($stored->ai_conversation_id);
    }

    public function test_applied_with_the_main_resume_defaults_to_the_current_version(): void
    {
        $current = $this->currentResumeVersion();
        $application = Application::factory()->create();
        $this->assertNotSame($current->id, $application->resume_version_id);

        $this->service()->markApplied($application);

        $this->assertSame($current->id, $application->fresh()->resume_version_id);
        $this->assertSame(ApplicationStatus::Applied, $application->fresh()->status);
    }

    public function test_marking_applied_records_the_given_date_and_notes(): void
    {
        $application = Application::factory()->create();

        $this->service()->markApplied($application, $application->resume_version_id, Carbon::parse('2026-02-03 09:00:00'), 'Via referral');

        $entry = $application->statusUpdates()->sole();

        $this->assertSame('2026-02-03 09:00:00', $entry->occurred_at->toDateTimeString());
        $this->assertSame('Via referral', $entry->notes);
    }

    public function test_marking_applied_with_an_unknown_resume_version_is_refused_and_changes_nothing(): void
    {
        $application = Application::factory()->create();
        $recordedVersionId = $application->resume_version_id;
        $unknownId = (int) ResumeVersion::query()->max('id') + 1000;

        try {
            $this->service()->markApplied($application, $unknownId);
            $this->fail('An application was marked applied with a resume version that does not exist.');
        } catch (ResumeVersionUnavailableException) {
            $stored = $application->fresh();

            $this->assertSame(ApplicationStatus::Draft, $stored->status);
            $this->assertSame($recordedVersionId, $stored->resume_version_id);
            $this->assertSame(0, $stored->statusUpdates()->count());
        }
    }

    public function test_applying_after_discarding_records_a_main_resume_version(): void
    {
        $application = Application::factory()->withTargetedResume()->withConversation()->create();
        $chosen = ResumeVersion::factory()->create();

        $this->service()->discardTargetedResume($application);
        $this->service()->markApplied($application->fresh(), $chosen->id);

        $stored = $application->fresh();

        $this->assertSame(ApplicationStatus::Applied, $stored->status);
        $this->assertSame($chosen->id, $stored->resume_version_id);
        $this->assertNull($stored->targeted_resume_id);
    }

    // ---------------------------------------------------------------------
    // Requirement: Application status history
    // ---------------------------------------------------------------------

    public function test_adding_a_status_entry(): void
    {
        $application = Application::factory()->applied(Carbon::parse('2026-03-01 10:00:00'))->create();

        $entry = $this->service()->addStatusUpdate(
            $application,
            ApplicationStatus::Interviewing,
            'Phone screen with the hiring manager',
            Carbon::parse('2026-03-09 15:00:00'),
        );

        $stored = ApplicationStatusUpdate::query()->findOrFail($entry->id);

        $this->assertSame($application->id, $stored->application_id);
        $this->assertSame(ApplicationStatus::Interviewing, $stored->status);
        $this->assertSame('Phone screen with the hiring manager', $stored->notes);
        $this->assertSame('2026-03-09 15:00:00', $stored->occurred_at->toDateTimeString());
        $this->assertSame(ApplicationStatus::Interviewing, $application->fresh()->status);
        $this->assertSame(ApplicationStatus::Interviewing, $application->status);
        $this->assertSame(
            [ApplicationStatus::Applied, ApplicationStatus::Interviewing],
            $application->fresh()->statusUpdates->pluck('status')->all(),
        );
        $this->assertTrue($application->fresh()->latestStatusUpdate->is($stored));
    }

    public function test_adding_a_status_entry_defaults_its_date_to_now(): void
    {
        $this->travelTo(Carbon::parse('2026-05-04 14:30:00'));
        $application = Application::factory()->applied(Carbon::parse('2026-03-01 10:00:00'))->create();

        $entry = $this->service()->addStatusUpdate($application, ApplicationStatus::Rejected);

        $this->assertSame('2026-05-04 14:30:00', $entry->fresh()->occurred_at->toDateTimeString());
        $this->assertNull($entry->fresh()->notes);
    }

    /**
     * @return array<string, array{0: ApplicationStatus}>
     */
    public static function terminalStatusProvider(): array
    {
        return [
            'rejected' => [ApplicationStatus::Rejected],
            'accepted' => [ApplicationStatus::Accepted],
            'hired' => [ApplicationStatus::Hired],
        ];
    }

    #[DataProvider('terminalStatusProvider')]
    public function test_terminal_application_refuses_a_new_entry_and_stores_nothing(ApplicationStatus $terminal): void
    {
        $application = Application::factory()->applied()->create();
        $this->service()->addStatusUpdate($application, $terminal);
        $entries = ApplicationStatusUpdate::query()->count();

        try {
            $this->service()->addStatusUpdate($application->fresh(), ApplicationStatus::Interviewing, 'Too late');
            $this->fail("A {$terminal->value} application accepted a status entry.");
        } catch (TerminalApplicationException $exception) {
            $this->assertInstanceOf(ApplicationException::class, $exception);
        }

        $this->assertSame($entries, ApplicationStatusUpdate::query()->count());
        $this->assertSame($terminal, $application->fresh()->status);
        $this->assertSame(2, $application->statusUpdates()->count());
    }

    #[DataProvider('terminalStatusProvider')]
    public function test_terminal_application_cannot_be_marked_applied(ApplicationStatus $terminal): void
    {
        $application = Application::factory()->applied()->create(['status' => $terminal]);
        $recordedVersionId = $application->resume_version_id;

        try {
            $this->service()->markApplied($application, ResumeVersion::factory()->create()->id);
            $this->fail("A {$terminal->value} application was marked applied.");
        } catch (TerminalApplicationException) {
            $this->assertSame($terminal, $application->fresh()->status);
            $this->assertSame($recordedVersionId, $application->fresh()->resume_version_id);
            $this->assertSame(1, $application->statusUpdates()->count());
        }
    }

    /**
     * @return array<string, array{0: ApplicationStatus}>
     */
    public static function nonPipelineStatusProvider(): array
    {
        return [
            'draft' => [ApplicationStatus::Draft],
            'passed' => [ApplicationStatus::Passed],
        ];
    }

    #[DataProvider('nonPipelineStatusProvider')]
    public function test_a_non_pipeline_status_cannot_be_a_history_entry(ApplicationStatus $status): void
    {
        $application = Application::factory()->applied()->create();
        $entries = ApplicationStatusUpdate::query()->count();

        try {
            $this->service()->addStatusUpdate($application, $status);
            $this->fail("A {$status->value} history entry was stored.");
        } catch (NonPipelineStatusException $exception) {
            $this->assertInstanceOf(ApplicationException::class, $exception);
        }

        $this->assertSame($entries, ApplicationStatusUpdate::query()->count());
        $this->assertSame(ApplicationStatus::Applied, $application->fresh()->status);
    }

    /**
     * The implementer's documented choice: which status may follow which is
     * advisory. Only terminal applications and non-pipeline statuses refuse.
     */
    public function test_adding_an_entry_does_not_enforce_allowed_next(): void
    {
        $application = Application::factory()->applied()->create();
        $this->assertNotContains(ApplicationStatus::Accepted, ApplicationStatus::Applied->allowedNext());

        $this->service()->addStatusUpdate($application, ApplicationStatus::Accepted);

        $this->assertSame(ApplicationStatus::Accepted, $application->fresh()->status);

        $draft = Application::factory()->create();
        $this->assertNotContains(ApplicationStatus::Interviewing, ApplicationStatus::Draft->allowedNext());

        $this->service()->addStatusUpdate($draft, ApplicationStatus::Interviewing);

        $this->assertSame(ApplicationStatus::Interviewing, $draft->fresh()->status);
        $this->assertFalse($draft->fresh()->hasBeenApplied());
    }

    public function test_adding_a_backdated_entry_still_sets_the_status_to_that_entry(): void
    {
        $application = Application::factory()->applied(Carbon::parse('2026-03-10 10:00:00'))->create();
        $this->service()->addStatusUpdate($application, ApplicationStatus::Interviewed, null, Carbon::parse('2026-03-20 10:00:00'));

        $this->service()->addStatusUpdate($application, ApplicationStatus::Interviewing, 'Forgot to log this', Carbon::parse('2026-03-15 10:00:00'));

        $this->assertSame(ApplicationStatus::Interviewing, $application->fresh()->status);
    }

    public function test_editing_an_entry_changes_its_notes_and_date_but_no_status(): void
    {
        $application = Application::factory()->applied(Carbon::parse('2026-03-01 10:00:00'))->create();
        $entry = $this->service()->addStatusUpdate($application, ApplicationStatus::Interviewing, 'Round one', Carbon::parse('2026-03-09 15:00:00'));

        $returned = $this->service()->updateStatusUpdate($application, $entry, 'Round one, moved a day', Carbon::parse('2026-03-10 16:00:00'));

        $stored = ApplicationStatusUpdate::query()->findOrFail($entry->id);

        $this->assertTrue($returned->is($stored));
        $this->assertSame('Round one, moved a day', $stored->notes);
        $this->assertSame('2026-03-10 16:00:00', $stored->occurred_at->toDateTimeString());
        $this->assertSame(ApplicationStatus::Interviewing, $stored->status);
        $this->assertSame(ApplicationStatus::Interviewing, $application->fresh()->status);
        $this->assertSame(2, $application->statusUpdates()->count());
    }

    public function test_editing_an_entry_can_clear_its_notes(): void
    {
        $application = Application::factory()->applied()->create();
        $entry = $this->service()->addStatusUpdate($application, ApplicationStatus::Interviewing, 'Round one', Carbon::parse('2026-03-09 15:00:00'));

        $this->service()->updateStatusUpdate($application, $entry, null, Carbon::parse('2026-03-09 15:00:00'));

        $this->assertNull($entry->fresh()->notes);
    }

    public function test_editing_an_entry_of_a_terminal_application_is_allowed(): void
    {
        $application = Application::factory()->applied(Carbon::parse('2026-03-01 10:00:00'))->create();
        $entry = $this->service()->addStatusUpdate($application, ApplicationStatus::Rejected, null, Carbon::parse('2026-03-09 15:00:00'));

        $this->service()->updateStatusUpdate($application->fresh(), $entry, 'Form rejection', Carbon::parse('2026-03-11 08:00:00'));

        $this->assertSame('Form rejection', $entry->fresh()->notes);
        $this->assertSame(ApplicationStatus::Rejected, $application->fresh()->status);
    }

    public function test_deleting_an_entry_sets_the_status_to_the_latest_remaining_entry(): void
    {
        $application = Application::factory()->applied(Carbon::parse('2026-03-01 10:00:00'))->create();
        $this->service()->addStatusUpdate($application, ApplicationStatus::Interviewing, null, Carbon::parse('2026-03-09 15:00:00'));
        $interviewed = $this->service()->addStatusUpdate($application, ApplicationStatus::Interviewed, null, Carbon::parse('2026-03-12 15:00:00'));

        $this->service()->deleteStatusUpdate($application, $interviewed);

        $this->assertNull(ApplicationStatusUpdate::query()->find($interviewed->id));
        $this->assertSame(ApplicationStatus::Interviewing, $application->fresh()->status);
        $this->assertSame(2, $application->statusUpdates()->count());
    }

    public function test_deleting_an_earlier_entry_keeps_the_status_of_the_latest_remaining_entry(): void
    {
        $application = Application::factory()->applied(Carbon::parse('2026-03-01 10:00:00'))->create();
        $interviewing = $this->service()->addStatusUpdate($application, ApplicationStatus::Interviewing, null, Carbon::parse('2026-03-09 15:00:00'));
        $this->service()->addStatusUpdate($application, ApplicationStatus::Interviewed, null, Carbon::parse('2026-03-12 15:00:00'));

        $this->service()->deleteStatusUpdate($application, $interviewing);

        $this->assertSame(ApplicationStatus::Interviewed, $application->fresh()->status);
    }

    public function test_deleting_the_terminal_entry_reopens_the_application(): void
    {
        $application = Application::factory()->applied(Carbon::parse('2026-03-01 10:00:00'))->create();
        $rejected = $this->service()->addStatusUpdate($application, ApplicationStatus::Rejected, null, Carbon::parse('2026-03-09 15:00:00'));

        $this->service()->deleteStatusUpdate($application->fresh(), $rejected);

        $reopened = $application->fresh();
        $this->assertSame(ApplicationStatus::Applied, $reopened->status);

        $this->service()->addStatusUpdate($reopened, ApplicationStatus::Interviewing);
        $this->assertSame(ApplicationStatus::Interviewing, $application->fresh()->status);
    }

    public function test_deleting_the_only_entry(): void
    {
        $application = Application::factory()->applied()->create();
        $only = $application->statusUpdates()->sole();

        $this->service()->deleteStatusUpdate($application, $only);

        $stored = $application->fresh();

        $this->assertSame(ApplicationStatus::Draft, $stored->status);
        $this->assertSame(0, $stored->statusUpdates()->count());
        $this->assertFalse($stored->hasBeenApplied());
    }

    public function test_deleting_the_only_entry_of_a_once_passed_application_returns_it_to_draft(): void
    {
        $application = Application::factory()->passed()->create();
        $this->service()->markApplied($application, $application->resume_version_id);

        $this->service()->deleteStatusUpdate($application->fresh(), $application->statusUpdates()->sole());

        $this->assertSame(ApplicationStatus::Draft, $application->fresh()->status);
    }

    public function test_entry_from_another_application_cannot_be_edited(): void
    {
        $application = Application::factory()->applied()->create();
        $other = Application::factory()->applied(Carbon::parse('2026-03-01 10:00:00'))->create();
        $foreign = $this->service()->addStatusUpdate($other, ApplicationStatus::Interviewing, 'Theirs', Carbon::parse('2026-03-09 15:00:00'));

        try {
            $this->service()->updateStatusUpdate($application, $foreign, 'Hijacked', Carbon::parse('2026-04-01 00:00:00'));
            $this->fail('A status entry was edited through an application it does not belong to.');
        } catch (ApplicationStatusUpdateMismatchException $exception) {
            $this->assertInstanceOf(ApplicationException::class, $exception);
        }

        $stored = ApplicationStatusUpdate::query()->findOrFail($foreign->id);

        $this->assertSame('Theirs', $stored->notes);
        $this->assertSame('2026-03-09 15:00:00', $stored->occurred_at->toDateTimeString());
        $this->assertSame($other->id, $stored->application_id);
        $this->assertSame(ApplicationStatus::Applied, $application->fresh()->status);
        $this->assertSame(ApplicationStatus::Interviewing, $other->fresh()->status);
    }

    public function test_entry_from_another_application_cannot_be_deleted(): void
    {
        $application = Application::factory()->applied()->create();
        $other = Application::factory()->applied(Carbon::parse('2026-03-01 10:00:00'))->create();
        $foreign = $this->service()->addStatusUpdate($other, ApplicationStatus::Interviewing, 'Theirs', Carbon::parse('2026-03-09 15:00:00'));

        try {
            $this->service()->deleteStatusUpdate($application, $foreign);
            $this->fail('A status entry was deleted through an application it does not belong to.');
        } catch (ApplicationStatusUpdateMismatchException) {
            $this->assertNotNull(ApplicationStatusUpdate::query()->find($foreign->id));
        }

        $this->assertSame(ApplicationStatus::Applied, $application->fresh()->status);
        $this->assertSame(1, $application->statusUpdates()->count());
        $this->assertSame(ApplicationStatus::Interviewing, $other->fresh()->status);
        $this->assertSame(2, $other->statusUpdates()->count());
    }

    // ---------------------------------------------------------------------
    // Requirement: Application Discussion page — Beginning analysis later
    // ---------------------------------------------------------------------

    public function test_beginning_analysis_later_attaches_a_session_to_the_same_application(): void
    {
        $this->currentResumeVersion();
        $application = $this->service()->createApplied(
            self::JOB_DESCRIPTION,
            'Acme Corp',
            'Staff Engineer',
            'Remote',
            occurredAt: Carbon::parse('2026-04-20 09:15:00'),
        );
        $this->service()->addStatusUpdate($application, ApplicationStatus::Interviewing, 'Round one', Carbon::parse('2026-04-27 09:15:00'));
        $history = $this->historySnapshot($application);
        $applications = Application::withTrashed()->count();
        $system = AiSystem::factory()->create();

        $returned = $this->service()->beginAnalysis($application->fresh(), $system);

        $stored = Application::query()->findOrFail($application->id);

        $this->assertSame($application->id, $returned->id);
        $this->assertSame($applications, Application::withTrashed()->count());
        $this->assertNotNull($stored->ai_conversation_id);
        $this->assertSame($system->id, $stored->conversation->ai_system_id);
        $this->assertSame('targeted-resume', $stored->conversation->feature);
        $this->assertSame(['step' => 'analysis', 'auto_start_pending' => true], $stored->conversation->context);
        $this->assertSame(ApplicationStatus::Interviewing, $stored->status);
        $this->assertSame($history, $this->historySnapshot($stored));
        $this->assertNull($stored->targeted_resume_id);

        $firstMessage = AiConversationMessage::query()
            ->where('ai_conversation_id', $stored->ai_conversation_id)
            ->where('role', 'user')
            ->sole();

        $this->assertStringContainsString(self::JOB_DESCRIPTION, $firstMessage->content);
        $this->assertStringContainsString('Acme Corp', $firstMessage->content);
    }

    public function test_beginning_analysis_is_refused_when_the_application_already_has_a_session(): void
    {
        $application = Application::factory()->withConversation()->create();
        $conversationId = $application->ai_conversation_id;
        $conversations = AiConversation::withTrashed()->count();

        try {
            $this->service()->beginAnalysis($application, AiSystem::factory()->create());
            $this->fail('A second AI session was attached to an application.');
        } catch (ApplicationAlreadyHasConversationException $exception) {
            $this->assertInstanceOf(ApplicationException::class, $exception);
        }

        $this->assertSame($conversationId, $application->fresh()->ai_conversation_id);
        $this->assertSame($conversations, AiConversation::withTrashed()->count());
    }

    public function test_beginning_analysis_is_refused_for_a_stale_copy_of_an_application_that_gained_a_session(): void
    {
        $application = Application::factory()->create();
        $stale = Application::query()->findOrFail($application->id);
        $system = AiSystem::factory()->create();

        $this->service()->beginAnalysis($application, $system);
        $conversationId = $application->fresh()->ai_conversation_id;

        try {
            $this->service()->beginAnalysis($stale, $system);
            $this->fail('A second AI session was attached to an application.');
        } catch (ApplicationAlreadyHasConversationException) {
            $this->assertSame($conversationId, $application->fresh()->ai_conversation_id);
        }
    }

    // ---------------------------------------------------------------------
    // Requirement: Finalizing a targeted resume attaches it to the Application
    // ---------------------------------------------------------------------

    public function test_first_finalize(): void
    {
        $application = Application::factory()->withConversation()->create();
        $documents = TargetedResume::query()->count();

        $targetedResume = $this->finalize($application, "Title: Staff Platform Engineer\n\n# Summary\nFirst draft");

        $stored = $application->fresh();

        $this->assertSame($documents + 1, TargetedResume::query()->count());
        $this->assertSame($targetedResume->id, $stored->targeted_resume_id);
        $this->assertSame(ApplicationStatus::Draft, $stored->status);
        $this->assertSame(0, $stored->statusUpdates()->count());
        $this->assertSame($stored->resume_version_id, $targetedResume->resume_version_id);
        $this->assertSame('Staff Platform Engineer', $targetedResume->title);
        $this->assertSame("# Summary\nFirst draft", data_get($targetedResume->tailored_data, 'markdown'));
    }

    public function test_finalize_after_applying(): void
    {
        $application = Application::factory()->withConversation()->create();
        $first = $this->finalize($application, "Title: Staff Platform Engineer\n\n# Summary\nFirst draft");
        $this->service()->markApplied($application->fresh(), null, Carbon::parse('2026-04-20 09:15:00'));
        $documents = TargetedResume::query()->count();

        $second = $this->finalize($application->fresh(), "Title: Principal Platform Engineer\n\n# Summary\nSecond draft");

        $stored = $application->fresh();

        $this->assertSame($first->id, $second->id);
        $this->assertSame($documents, TargetedResume::query()->count());
        $this->assertSame($first->id, $stored->targeted_resume_id);
        $this->assertSame('Principal Platform Engineer', $second->title);
        $this->assertSame("# Summary\nSecond draft", data_get(TargetedResume::query()->findOrFail($first->id)->tailored_data, 'markdown'));
        $this->assertSame("# Summary\nSecond draft", data_get(TargetedResume::query()->findOrFail($first->id)->tailored_data, 'content'));
        $this->assertSame(ApplicationStatus::Applied, $stored->status);
        $this->assertSame([ApplicationStatus::Applied], $stored->statusUpdates->pluck('status')->all());
        $this->assertSame('2026-04-20 09:15:00', $stored->statusUpdates->first()->occurred_at->toDateTimeString());
    }

    public function test_finalize_after_applying_with_the_main_resume_attaches_a_document_without_changing_status(): void
    {
        $application = Application::factory()->withConversation()->create();
        $this->service()->markApplied($application, $application->resume_version_id);
        $this->service()->addStatusUpdate($application, ApplicationStatus::Interviewing);

        $targetedResume = $this->finalize($application->fresh(), "Title: Engineer\n\n# Summary\nLate draft");

        $this->assertSame($targetedResume->id, $application->fresh()->targeted_resume_id);
        $this->assertSame(ApplicationStatus::Interviewing, $application->fresh()->status);
        $this->assertSame(2, $application->statusUpdates()->count());
    }

    public function test_finalize_replaces_stale_rendered_documents(): void
    {
        $application = Application::factory()->withTargetedResume()->withConversation()->create();
        [$docxPath, $pdfPath] = $this->renderedDocuments($application->targetedResume);

        $targetedResume = $this->finalize($application->fresh(), "Title: Engineer\n\n# Summary\nReplacement");

        $this->assertNull($targetedResume->docx_path);
        $this->assertNull($targetedResume->pdf_path);
        $this->assertFileDoesNotExist($docxPath);
        $this->assertFileDoesNotExist($pdfPath);
    }

    // ---------------------------------------------------------------------
    // Requirement: Discarding a targeted resume
    // ---------------------------------------------------------------------

    public function test_discarding_before_applying(): void
    {
        $application = Application::factory()->withTargetedResume()->withConversation()->create(['fit_score' => 70]);
        $targetedResume = $application->targetedResume;
        $recordedVersionId = $application->resume_version_id;
        $conversationId = $application->ai_conversation_id;
        $letter = $this->coverLetter($application);
        [$docxPath, $pdfPath] = $this->renderedDocuments($targetedResume);

        $returned = $this->service()->discardTargetedResume($application);

        $stored = Application::query()->findOrFail($application->id);

        $this->assertTrue($returned->is($stored));
        $this->assertNull(TargetedResume::query()->find($targetedResume->id));
        $this->assertDatabaseMissing('targeted_resumes', ['id' => $targetedResume->id]);
        $this->assertFileDoesNotExist($docxPath);
        $this->assertFileDoesNotExist($pdfPath);

        $this->assertNull($stored->targeted_resume_id);
        $this->assertNull($stored->targetedResume);
        $this->assertNull($application->targeted_resume_id);
        $this->assertSame(ApplicationStatus::Draft, $stored->status);
        $this->assertSame(0, $stored->statusUpdates()->count());
        $this->assertSame($recordedVersionId, $stored->resume_version_id);
        $this->assertSame($conversationId, $stored->ai_conversation_id);
        $this->assertNotNull(AiConversation::query()->find($conversationId));
        $this->assertSame(70, $stored->fit_score);

        $storedLetter = CoverLetter::query()->findOrFail($letter->id);
        $this->assertSame($stored->id, $storedLetter->application_id);
        $this->assertSame('Letter body.', $storedLetter->message_body);
        $this->assertSame(1, $stored->coverLetters()->count());

        $this->assertNull(AiConversation::query()->findOrFail($conversationId)->targetedResume);
    }

    public function test_discarding_before_applying_keeps_a_passed_application_passed(): void
    {
        $application = Application::factory()->passed()->withTargetedResume()->create();

        $this->service()->discardTargetedResume($application);

        $this->assertSame(ApplicationStatus::Passed, $application->fresh()->status);
        $this->assertNull($application->fresh()->targeted_resume_id);
    }

    public function test_discarding_through_the_targeted_resume_itself(): void
    {
        $application = Application::factory()->withTargetedResume()->withConversation()->create();
        $targetedResume = TargetedResume::query()->findOrFail($application->targeted_resume_id);

        $returned = $this->service()->discardTargetedResume($targetedResume);

        $this->assertSame($application->id, $returned->id);
        $this->assertNull(TargetedResume::query()->find($targetedResume->id));
        $this->assertNull($application->fresh()->targeted_resume_id);
        $this->assertSame(ApplicationStatus::Draft, $application->fresh()->status);
    }

    public function test_discard_refused_after_applying(): void
    {
        $application = Application::factory()->withTargetedResume()->withConversation()->applied()->create();
        $targetedResume = $application->targetedResume;
        [$docxPath, $pdfPath] = $this->renderedDocuments($targetedResume);
        $messages = AiConversationMessage::query()->where('ai_conversation_id', $application->ai_conversation_id)->count();

        try {
            $this->service()->discardTargetedResume($application);
            $this->fail('The targeted resume of an applied application was discarded.');
        } catch (TargetedResumeAlreadySentException $exception) {
            $this->assertInstanceOf(ApplicationException::class, $exception);
        }

        $stored = $application->fresh();
        $storedResume = TargetedResume::query()->find($targetedResume->id);

        $this->assertNotNull($storedResume);
        $this->assertSame($targetedResume->id, $stored->targeted_resume_id);
        $this->assertSame($docxPath, $storedResume->docx_path);
        $this->assertSame($pdfPath, $storedResume->pdf_path);
        $this->assertFileExists($docxPath);
        $this->assertFileExists($pdfPath);
        $this->assertSame(ApplicationStatus::Applied, $stored->status);
        $this->assertSame($messages, AiConversationMessage::query()->where('ai_conversation_id', $application->ai_conversation_id)->count());
    }

    public function test_discard_refused_after_applying_when_requested_through_the_targeted_resume(): void
    {
        $application = Application::factory()->withTargetedResume()->applied()->create();
        $targetedResume = TargetedResume::query()->findOrFail($application->targeted_resume_id);
        [$docxPath, $pdfPath] = $this->renderedDocuments($targetedResume);

        try {
            $this->service()->discardTargetedResume($targetedResume);
            $this->fail('The targeted resume of an applied application was discarded.');
        } catch (TargetedResumeAlreadySentException) {
            $this->assertNotNull(TargetedResume::query()->find($targetedResume->id));
            $this->assertSame($targetedResume->id, $application->fresh()->targeted_resume_id);
            $this->assertFileExists($docxPath);
            $this->assertFileExists($pdfPath);
        }
    }

    public function test_discard_refused_once_an_applied_entry_exists_whatever_the_current_status(): void
    {
        $application = Application::factory()->withTargetedResume()->applied()->create();
        $this->service()->addStatusUpdate($application, ApplicationStatus::Interviewing);
        $this->service()->addStatusUpdate($application, ApplicationStatus::Rejected);

        try {
            $this->service()->discardTargetedResume($application->fresh());
            $this->fail('The targeted resume of a rejected, once-applied application was discarded.');
        } catch (TargetedResumeAlreadySentException) {
            $this->assertNotNull($application->fresh()->targeted_resume_id);
        }
    }

    public function test_discard_refused_for_an_application_with_no_targeted_resume(): void
    {
        $application = Application::factory()->withConversation()->create();
        $messages = AiConversationMessage::query()->where('ai_conversation_id', $application->ai_conversation_id)->count();

        try {
            $this->service()->discardTargetedResume($application);
            $this->fail('A discard succeeded for an application with no targeted resume.');
        } catch (TargetedResumeMissingException $exception) {
            $this->assertInstanceOf(ApplicationException::class, $exception);
        }

        $this->assertSame($messages, AiConversationMessage::query()->where('ai_conversation_id', $application->ai_conversation_id)->count());
    }

    public function test_agent_is_told_when_a_targeted_resume_is_discarded(): void
    {
        $application = Application::factory()->withTargetedResume()->withConversation()->create();
        $conversationId = $application->ai_conversation_id;
        $targetedResumeId = $application->targeted_resume_id;
        AiConversationMessage::create(['ai_conversation_id' => $conversationId, 'role' => 'user', 'content' => 'Earlier turn']);
        $messageIds = AiConversationMessage::query()->where('ai_conversation_id', $conversationId)->pluck('id');
        $llmMessages = AiLlmMessage::query()->where('ai_conversation_id', $conversationId)->count();
        $interactions = AiInteractionLog::query()->where('ai_conversation_id', $conversationId)->count();
        $conversationStatus = AiConversation::query()->findOrFail($conversationId)->status;

        $this->service()->discardTargetedResume($application);

        $added = AiConversationMessage::query()
            ->where('ai_conversation_id', $conversationId)
            ->whereNotIn('id', $messageIds)
            ->get();

        $this->assertCount(1, $added, 'Exactly one message — the discard note, and no assistant turn — should be added.');

        $note = $added->first();

        $this->assertSame('resume_discarded', data_get($note->metadata, 'origin'));
        $this->assertSame($targetedResumeId, data_get($note->metadata, 'targeted_resume_id'));
        $this->assertStringContainsStringIgnoringCase('discarded', $note->content);
        $this->assertNotSame('assistant', $note->role);
        $this->assertSame($llmMessages, AiLlmMessage::query()->where('ai_conversation_id', $conversationId)->count());
        $this->assertSame($interactions, AiInteractionLog::query()->where('ai_conversation_id', $conversationId)->count());
        $this->assertSame($conversationStatus, AiConversation::query()->findOrFail($conversationId)->status);
    }

    /**
     * The implementer's documented choice: the note is recorded with the
     * `user` role, like the manual-edit note.
     */
    public function test_discard_note_is_recorded_with_the_user_role(): void
    {
        $application = Application::factory()->withTargetedResume()->withConversation()->create();

        $this->service()->discardTargetedResume($application);

        $note = AiConversationMessage::query()
            ->where('ai_conversation_id', $application->ai_conversation_id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('resume_discarded', data_get($note->metadata, 'origin'));
        $this->assertSame('user', $note->role);
    }

    public function test_discard_without_a_session_records_no_message_and_raises_no_error(): void
    {
        $application = Application::factory()->withTargetedResume()->create();
        $targetedResumeId = $application->targeted_resume_id;
        $messages = AiConversationMessage::query()->count();

        $returned = $this->service()->discardTargetedResume($application);

        $this->assertSame($application->id, $returned->id);
        $this->assertNull(TargetedResume::query()->find($targetedResumeId));
        $this->assertNull($application->fresh()->targeted_resume_id);
        $this->assertNull($application->fresh()->ai_conversation_id);
        $this->assertSame($messages, AiConversationMessage::query()->count());
    }

    public function test_building_a_new_one_afterwards(): void
    {
        $application = Application::factory()->withConversation()->create();
        $first = $this->finalize($application, "Title: Staff Platform Engineer\n\n# Summary\nFirst draft");

        $this->service()->discardTargetedResume($application->fresh());
        $this->assertNull($application->fresh()->targeted_resume_id);

        $second = $this->finalize($application->fresh(), "Title: Principal Platform Engineer\n\n# Summary\nSecond draft");

        $stored = $application->fresh();

        $this->assertNotSame($first->id, $second->id);
        $this->assertNull(TargetedResume::query()->find($first->id));
        $this->assertNotNull(TargetedResume::query()->find($second->id));
        $this->assertSame($second->id, $stored->targeted_resume_id);
        $this->assertTrue($second->application->is($stored));
        $this->assertSame("# Summary\nSecond draft", data_get($second->tailored_data, 'markdown'));
        $this->assertSame(ApplicationStatus::Draft, $stored->status);
    }

    // ---------------------------------------------------------------------
    // Requirement: Deleting an application
    // ---------------------------------------------------------------------

    public function test_deleted_application(): void
    {
        $application = Application::factory()->withTargetedResume()->withConversation()->applied()->create();
        $conversationId = $application->ai_conversation_id;
        $targetedResumeId = $application->targeted_resume_id;

        $this->service()->delete($application);

        $this->assertNull(Application::query()->find($application->id));
        $this->assertFalse(Application::query()->whereKey($application->id)->exists());
        $this->assertSoftDeleted('applications', ['id' => $application->id]);

        $this->assertNull(AiConversation::query()->find($conversationId));
        $this->assertNotNull(AiConversation::withTrashed()->findOrFail($conversationId)->deleted_at);

        $trashed = Application::withTrashed()->findOrFail($application->id);

        $this->assertNotNull($trashed->deleted_at);
        $this->assertSame($conversationId, $trashed->ai_conversation_id);
        $this->assertSame($targetedResumeId, $trashed->targeted_resume_id);
        $this->assertSame(ApplicationStatus::Applied, $trashed->status);
        $this->assertSame(1, $trashed->statusUpdates()->count());
        $this->assertNotNull(TargetedResume::query()->find($targetedResumeId));

        $trashed->restore();

        $this->assertNotNull(Application::query()->find($application->id));
    }

    public function test_deleted_application_without_a_session(): void
    {
        $application = Application::factory()->applied()->create();

        $this->service()->delete($application);

        $this->assertSoftDeleted('applications', ['id' => $application->id]);
        $this->assertNotNull(Application::withTrashed()->find($application->id));
    }

    // ---------------------------------------------------------------------
    // Transactions
    // ---------------------------------------------------------------------

    public function test_begin_analysis_leaves_no_application_behind_when_the_session_cannot_be_started(): void
    {
        $this->currentResumeVersion();
        $applications = Application::withTrashed()->count();
        $conversations = AiConversation::withTrashed()->count();

        $failing = Mockery::mock(TargetedResumeService::class);
        $failing->shouldReceive('startConversation')->once()->andThrow(new RuntimeException('Session could not be started.'));

        try {
            (new ApplicationService($failing))->createForAnalysis(AiSystem::factory()->create(), self::JOB_DESCRIPTION, 'Acme Corp', 'Engineer');
            $this->fail('The failure was swallowed.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Session could not be started.', $exception->getMessage());
        }

        $this->assertSame($applications, Application::withTrashed()->count());
        $this->assertSame($conversations, AiConversation::withTrashed()->count());
    }

    public function test_discard_is_rolled_back_when_the_session_note_cannot_be_recorded(): void
    {
        $application = Application::factory()->withTargetedResume()->withConversation()->create();
        $targetedResumeId = $application->targeted_resume_id;

        $failing = Mockery::mock(TargetedResumeService::class);
        $failing->shouldReceive('recordResumeDiscardedMessage')->once()->andThrow(new RuntimeException('Note could not be recorded.'));

        try {
            (new ApplicationService($failing))->discardTargetedResume($application);
            $this->fail('The failure was swallowed.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Note could not be recorded.', $exception->getMessage());
        }

        $this->assertNotNull(TargetedResume::query()->find($targetedResumeId));
        $this->assertSame($targetedResumeId, Application::query()->findOrFail($application->id)->targeted_resume_id);
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function service(): ApplicationService
    {
        return app(ApplicationService::class);
    }

    private function finalize(Application $application, string $content): TargetedResume
    {
        return app(TargetedResumeService::class)->saveTailoredResume($application, $content);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function historySnapshot(Application $application): array
    {
        return ApplicationStatusUpdate::query()
            ->where('application_id', $application->id)
            ->orderBy('id')
            ->get()
            ->map(fn (ApplicationStatusUpdate $entry): array => [
                'id' => $entry->id,
                'status' => $entry->status->value,
                'notes' => $entry->notes,
                'occurred_at' => $entry->occurred_at->toDateTimeString(),
            ])
            ->all();
    }

    private function currentResumeVersion(): ResumeVersion
    {
        ResumeVersion::query()->update(['is_current' => false]);

        return ResumeVersion::factory()->create(['is_current' => true]);
    }

    /**
     * Give a targeted resume real rendered files, so their removal can be
     * observed on disk.
     *
     * @return array{0: string, 1: string}
     */
    private function renderedDocuments(TargetedResume $targetedResume): array
    {
        $paths = [];

        foreach (['docx', 'pdf'] as $extension) {
            $path = sys_get_temp_dir().'/application-service-test-'.bin2hex(random_bytes(8)).'.'.$extension;
            file_put_contents($path, 'rendered');
            $this->temporaryFiles[] = $path;
            $paths[] = $path;
        }

        $targetedResume->forceFill(['docx_path' => $paths[0], 'pdf_path' => $paths[1]])->save();

        return $paths;
    }

    private function coverLetter(Application $application): CoverLetter
    {
        return CoverLetter::create([
            'resume_version_id' => $application->resume_version_id,
            'application_id' => $application->id,
            'company_name' => $application->company_name,
            'position' => $application->position,
            'date' => '2026-03-01',
            'greeting' => 'Hello,',
            'message_body' => 'Letter body.',
        ]);
    }
}
