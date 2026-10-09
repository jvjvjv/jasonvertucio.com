<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Models\AiConversation;
use App\Models\Application;
use App\Models\ApplicationStatusUpdate;
use App\Models\ResumeVersion;
use App\Models\TargetedResume;
use App\Models\User;
use App\Support\AnalysisSystemGuard;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Jvjvjv\CodeTalker\Enums\AiConversationStatus;
use Jvjvjv\CodeTalker\Models\AiSystem;
use Jvjvjv\CodeTalker\Models\AiSystemFeatureDefault;
use Jvjvjv\CodeTalker\Services\LaravelAi\AgentFactory;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * HTTP-level scenarios for creating an application and moving it through
 * its first decisions: "Starting a new session", "Marking an application
 * applied confirms the resume used", passing, editing details and deleting.
 */
class ApplicationCreateTest extends TestCase
{
    use DatabaseTransactions;

    private const string JOB_DESCRIPTION = 'Build and maintain Laravel applications.';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(AgentFactory::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('forSystem');
        });

        AiSystemFeatureDefault::query()->delete();

        $this->admin = User::factory()->create();
        Permission::firstOrCreate(['name' => 'edit-resume']);
        $this->admin->givePermissionTo('edit-resume');
    }

    protected function tearDown(): void
    {
        $this->travelBack();

        parent::tearDown();
    }

    // ---------------------------------------------------------------------
    // Requirement: Starting a new session
    // ---------------------------------------------------------------------

    public function test_new_session_page_defaults_to_the_current_resume_version_and_the_feature_default_systems(): void
    {
        $current = $this->currentResumeVersion();
        $older = ResumeVersion::factory()->create();
        $resumeSystem = AiSystem::factory()->create(['name' => 'Createzq Resume System']);
        $plainSystem = AiSystem::factory()->create(['name' => 'Createzq Plain System', 'system_prompt_id' => null]);
        $inactive = AiSystem::factory()->inactive()->create(['name' => 'Createzq Inactive System']);
        AiSystemFeatureDefault::create(['ai_system_id' => $resumeSystem->id, 'feature' => 'targeted-resume']);
        AiSystemFeatureDefault::create(['ai_system_id' => $resumeSystem->id, 'feature' => 'cover-letter']);
        AiSystemFeatureDefault::create(['ai_system_id' => $inactive->id, 'feature' => 'chat']);

        $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('resume/applications/Create', false)
                ->where('currentResumeVersionId', $current->id)
                ->where('defaultSystemId', $resumeSystem->id)
                ->where('coverLetterDefaultId', $resumeSystem->id)
                ->where('resumeVersions', function ($versions) use ($current, $older): bool {
                    $versions = collect($versions)->keyBy('id');

                    return $versions[$current->id] === ['id' => $current->id, 'version' => $current->version, 'is_current' => true]
                        && $versions[$older->id] === ['id' => $older->id, 'version' => $older->version, 'is_current' => false];
                })
                ->where('systems', function ($systems) use ($resumeSystem, $plainSystem, $inactive): bool {
                    $systems = collect($systems)->keyBy('id');

                    return $systems->has($resumeSystem->id)
                        && $systems[$resumeSystem->id] === ['id' => $resumeSystem->id, 'name' => 'Createzq Resume System', 'model' => $resumeSystem->model]
                        && ! $systems->has($plainSystem->id)
                        && ! $systems->has($inactive->id);
                })
            );
    }

    public function test_begin_analysis(): void
    {
        $current = $this->currentResumeVersion();
        $system = AiSystem::factory()->create();
        $applications = Application::withTrashed()->count();

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.store'), [
                'intent' => 'analyze',
                'ai_system_id' => $system->id,
                'job_description' => self::JOB_DESCRIPTION,
                'job_title' => 'Staff Engineer',
                'company_name' => 'Createzq Analysis Co',
                'job_location' => 'Philadelphia, PA',
            ]);

        $response->assertOk();
        $this->assertSame($applications + 1, Application::withTrashed()->count());

        $application = Application::query()->where('company_name', 'Createzq Analysis Co')->sole();

        $response->assertExactJson([
            'application_id' => $application->id,
            'redirect' => route('admin.resume.applications.show', $application),
        ]);

        $this->assertSame(ApplicationStatus::Draft, $application->status);
        $this->assertSame('Staff Engineer', $application->position);
        $this->assertSame('Philadelphia, PA', $application->location);
        $this->assertSame(self::JOB_DESCRIPTION, $application->job_description);
        $this->assertSame($current->id, $application->resume_version_id);
        $this->assertNull($application->targeted_resume_id);
        $this->assertSame(0, $application->statusUpdates()->count());

        $conversation = AiConversation::query()->findOrFail($application->ai_conversation_id);

        $this->assertSame($system->id, $conversation->ai_system_id);
        $this->assertSame($this->admin->id, $conversation->user_id);
        $this->assertSame(AiConversationStatus::Active, $conversation->status);
        $this->assertSame(['step' => 'analysis', 'auto_start_pending' => true], $conversation->context);

        $this->actingAs($this->admin)
            ->get($response->json('redirect'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('resume/applications/Show', false)
                ->where('application.id', $application->id)
                ->where('application.status', 'draft')
                ->where('conversation.id', $conversation->id)
                ->where('shouldAutoStart', true)
                ->has('messages', 1)
                ->where('messages.0.role', 'user')
            );
    }

    public function test_begin_analysis_ignores_a_resume_version_and_an_applied_date(): void
    {
        $current = $this->currentResumeVersion();
        $other = ResumeVersion::factory()->create();

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.store'), [
                'intent' => 'analyze',
                'ai_system_id' => AiSystem::factory()->create()->id,
                'job_description' => self::JOB_DESCRIPTION,
                'resume_version_id' => $other->id,
                'occurred_at' => '2026-07-01',
            ])
            ->assertOk();

        $application = Application::query()->findOrFail($response->json('application_id'));

        $this->assertSame(ApplicationStatus::Draft, $application->status);
        $this->assertSame($current->id, $application->resume_version_id);
        $this->assertSame(0, $application->statusUpdates()->count());
    }

    public function test_i_applied(): void
    {
        $this->travelTo(Carbon::parse('2026-05-04 14:30:00'));
        $current = $this->currentResumeVersion();
        ResumeVersion::factory()->create();
        $conversations = AiConversation::withTrashed()->count();
        $documents = TargetedResume::query()->count();

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.store'), [
                'intent' => 'applied',
                'job_description' => self::JOB_DESCRIPTION,
                'job_title' => 'Staff Engineer',
                'company_name' => 'Createzq Applied Co',
            ]);

        $response->assertOk();

        $application = Application::query()->where('company_name', 'Createzq Applied Co')->sole();

        $response->assertExactJson([
            'application_id' => $application->id,
            'redirect' => route('admin.resume.applications.show', $application),
        ]);

        $this->assertSame($current->id, $application->resume_version_id);
        $this->assertNull($application->ai_conversation_id);
        $this->assertNull($application->targeted_resume_id);
        $this->assertSame(ApplicationStatus::Applied, $application->status);
        $this->assertSame($conversations, AiConversation::withTrashed()->count());
        $this->assertSame($documents, TargetedResume::query()->count());

        $entries = $application->statusUpdates()->get();

        $this->assertCount(1, $entries);
        $this->assertSame(ApplicationStatus::Applied, $entries[0]->status);
        $this->assertSame('2026-05-04 14:30:00', $entries[0]->occurred_at->toDateTimeString());

        $this->actingAs($this->admin)
            ->get($response->json('redirect'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('resume/applications/Show', false)
                ->where('application.id', $application->id)
                ->where('application.status', 'applied')
                ->where('application.resume_version', ['id' => $current->id, 'version' => $current->version])
                ->where('conversation', null)
                ->where('shouldAutoStart', false)
            );
    }

    public function test_i_applied_needs_no_ai_system_and_is_not_blocked_by_the_separate_models_guard(): void
    {
        $this->currentResumeVersion();
        $this->separateFeatureDefaults();

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.store'), [
                'intent' => 'applied',
                'job_description' => self::JOB_DESCRIPTION,
            ])
            ->assertOk();

        $application = Application::query()->findOrFail($response->json('application_id'));

        $this->assertSame(ApplicationStatus::Applied, $application->status);
        $this->assertSame(Application::UNKNOWN_COMPANY, $application->company_name);
        $this->assertSame(Application::UNKNOWN_POSITION, $application->position);
    }

    /**
     * @return array<string, array{0: string, 1: array<string, mixed>}>
     */
    public static function missingJobDescriptionProvider(): array
    {
        return [
            'begin analysis, absent' => ['analyze', []],
            'begin analysis, null' => ['analyze', ['job_description' => null]],
            'begin analysis, blank' => ['analyze', ['job_description' => '   ']],
            'i applied, absent' => ['applied', []],
            'i applied, null' => ['applied', ['job_description' => null]],
            'i applied, blank' => ['applied', ['job_description' => '   ']],
        ];
    }

    /**
     * @param  array<string, mixed>  $jobDescription
     */
    #[DataProvider('missingJobDescriptionProvider')]
    public function test_missing_job_description(string $intent, array $jobDescription): void
    {
        $this->currentResumeVersion();
        $applications = Application::withTrashed()->count();
        $conversations = AiConversation::withTrashed()->count();

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.store'), [
                'intent' => $intent,
                'ai_system_id' => AiSystem::factory()->create()->id,
                'job_title' => 'Staff Engineer',
                'company_name' => 'Createzq Missing Co',
                ...$jobDescription,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['job_description'])
            ->assertJsonPath('errors.job_description.0', 'A job description is required.');

        $this->assertSame($applications, Application::withTrashed()->count());
        $this->assertSame($conversations, AiConversation::withTrashed()->count());
        $this->assertDatabaseMissing('applications', ['company_name' => 'Createzq Missing Co']);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidNewSessionProvider(): array
    {
        return [
            'analyze without an ai system' => [['intent' => 'analyze'], 'ai_system_id'],
            'analyze with a null ai system' => [['intent' => 'analyze', 'ai_system_id' => null], 'ai_system_id'],
            'analyze with an unknown ai system' => [['intent' => 'analyze', 'ai_system_id' => 999999999], 'ai_system_id'],
            'applied with an unknown resume version' => [['intent' => 'applied', 'resume_version_id' => 999999999], 'resume_version_id'],
            'analyze with an unknown resume version' => [['intent' => 'analyze', 'ai_system_id' => 'valid', 'resume_version_id' => 999999999], 'resume_version_id'],
            'unknown intent' => [['intent' => 'maybe'], 'intent'],
            'missing intent' => [[], 'intent'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('invalidNewSessionProvider')]
    public function test_new_session_is_refused_for_invalid_input(array $payload, string $errorKey): void
    {
        $this->currentResumeVersion();

        if (($payload['ai_system_id'] ?? null) === 'valid') {
            $payload['ai_system_id'] = AiSystem::factory()->create()->id;
        }

        $applications = Application::withTrashed()->count();
        $conversations = AiConversation::withTrashed()->count();

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.store'), [
                'job_description' => self::JOB_DESCRIPTION,
                'company_name' => 'Createzq Invalid Co',
                ...$payload,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors([$errorKey]);

        $this->assertSame($applications, Application::withTrashed()->count());
        $this->assertSame($conversations, AiConversation::withTrashed()->count());
    }

    public function test_begin_analysis_is_refused_while_the_resume_and_cover_letter_defaults_are_different_systems(): void
    {
        $this->currentResumeVersion();
        [$resumeSystem] = $this->separateFeatureDefaults();
        $applications = Application::withTrashed()->count();
        $conversations = AiConversation::withTrashed()->count();

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.store'), [
                'intent' => 'analyze',
                'ai_system_id' => $resumeSystem->id,
                'job_description' => self::JOB_DESCRIPTION,
            ])
            ->assertStatus(422)
            ->assertExactJson(['error' => AnalysisSystemGuard::SEPARATE_MODELS_MESSAGE]);

        $this->assertSame('Separate models for Targeted Resume and Cover Letter are unsupported at this time.', AnalysisSystemGuard::SEPARATE_MODELS_MESSAGE);
        $this->assertSame($applications, Application::withTrashed()->count());
        $this->assertSame($conversations, AiConversation::withTrashed()->count());
    }

    public function test_begin_analysis_is_refused_without_a_current_resume_version(): void
    {
        ResumeVersion::query()->update(['is_current' => false]);
        $applications = Application::withTrashed()->count();

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.store'), [
                'intent' => 'analyze',
                'ai_system_id' => AiSystem::factory()->create()->id,
                'job_description' => self::JOB_DESCRIPTION,
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['message']);

        $this->assertSame($applications, Application::withTrashed()->count());
    }

    // ---------------------------------------------------------------------
    // Requirement: Marking an application applied confirms the resume used
    // ---------------------------------------------------------------------

    public function test_applied_with_the_targeted_resume(): void
    {
        $application = Application::factory()->withTargetedResume()->withConversation()->create();
        $targetedResumeId = $application->targeted_resume_id;
        $recordedVersionId = $application->resume_version_id;
        $sent = ResumeVersion::factory()->create();

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.apply', $application), ['resume_version_id' => $sent->id])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'applied')
            ->assertJsonCount(1, 'status_updates')
            ->assertJsonPath('status_updates.0.status', 'applied')
            ->assertJsonPath('allowed_next_statuses', ['interviewing', 'offered', 'rejected']);

        $application->refresh();

        $this->assertSame(ApplicationStatus::Applied, $application->status);
        $this->assertSame($targetedResumeId, $application->targeted_resume_id);
        $this->assertSame($recordedVersionId, $application->resume_version_id);
        $this->assertNotNull(TargetedResume::query()->find($targetedResumeId));

        $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.show', $application))
            ->assertInertia(fn (Assert $page) => $page
                ->where('targetedResume.id', $targetedResumeId)
                ->where('application.has_applied', true)
            );
    }

    public function test_applied_with_the_main_resume_after_an_analysis(): void
    {
        $this->travelTo(Carbon::parse('2026-05-04 14:30:00'));
        $application = Application::factory()->withConversation()->create();
        $chosen = ResumeVersion::factory()->create();
        $documents = TargetedResume::query()->count();

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.apply', $application), ['resume_version_id' => $chosen->id])
            ->assertOk()
            ->assertJsonPath('status', 'applied')
            ->assertJsonCount(1, 'status_updates');

        $application->refresh();

        $this->assertSame(ApplicationStatus::Applied, $application->status);
        $this->assertSame($chosen->id, $application->resume_version_id);
        $this->assertNull($application->targeted_resume_id);
        $this->assertSame($documents, TargetedResume::query()->count());
        $this->assertNotNull($application->ai_conversation_id);
        $this->assertSame('2026-05-04 14:30:00', $application->statusUpdates()->sole()->occurred_at->toDateTimeString());

        $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.show', $application))
            ->assertInertia(fn (Assert $page) => $page
                ->where('targetedResume', null)
                ->where('application.resume_version', ['id' => $chosen->id, 'version' => $chosen->version])
            );
    }

    public function test_applying_after_discarding_records_a_main_resume_version(): void
    {
        $application = Application::factory()->withTargetedResume()->create();
        $chosen = ResumeVersion::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.resume.targeted.destroy', $application->targeted_resume_id))
            ->assertRedirect(route('admin.resume.applications.show', $application));

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.apply', $application), ['resume_version_id' => $chosen->id])
            ->assertOk()
            ->assertJsonPath('status', 'applied');

        $application->refresh();

        $this->assertSame($chosen->id, $application->resume_version_id);
        $this->assertNull($application->targeted_resume_id);
    }

    public function test_applying_after_passing(): void
    {
        $application = Application::factory()->passed()->create();

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.apply', $application), ['resume_version_id' => $application->resume_version_id])
            ->assertOk()
            ->assertJsonPath('status', 'applied')
            ->assertJsonCount(1, 'status_updates')
            ->assertJsonPath('status_updates.0.status', 'applied');

        $this->assertSame(ApplicationStatus::Applied, $application->fresh()->status);
    }

    // ---------------------------------------------------------------------
    // Requirement: Application statuses — passing
    // ---------------------------------------------------------------------

    public function test_passing_on_a_job(): void
    {
        $application = Application::factory()->withConversation()->create();

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.pass', $application))
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'status' => 'passed',
                'redirect' => route('admin.resume.applications.index'),
            ]);

        $this->assertSame(ApplicationStatus::Passed, $application->fresh()->status);
        $this->assertSame(0, $application->statusUpdates()->count());
        $this->assertSame(AiConversationStatus::Pass, AiConversation::query()->findOrFail($application->ai_conversation_id)->status);
    }

    public function test_passing_on_a_job_without_a_session(): void
    {
        $application = Application::factory()->create();

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.pass', $application))
            ->assertOk()
            ->assertJsonPath('status', 'passed');

        $this->assertSame(ApplicationStatus::Passed, $application->fresh()->status);
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
    public function test_passing_is_refused_with_a_conflict_once_in_the_pipeline(ApplicationStatus $status): void
    {
        $application = Application::factory()->applied()->create(['status' => $status]);

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.pass', $application))
            ->assertStatus(409)
            ->assertJsonStructure(['message']);

        $this->assertSame($status, $application->fresh()->status);
    }

    // ---------------------------------------------------------------------
    // Requirement: Application Discussion page — editing job details
    // ---------------------------------------------------------------------

    public function test_editing_job_details_updates_the_application(): void
    {
        $application = Application::factory()->withTargetedResume()->withConversation()->create([
            'company_name' => 'Old Company',
            'position' => 'Old Position',
            'location' => 'Old Town',
            'job_description' => 'Old description',
            'fit_score' => 40,
            'fit_summary' => 'Old summary',
        ]);
        $document = TargetedResume::query()->findOrFail($application->targeted_resume_id);

        $this->actingAs($this->admin)
            ->putJson(route('admin.resume.applications.update', $application), [
                'company_name' => 'New Company',
                'job_title' => 'New Position',
                'location' => 'New City',
                'job_description' => 'New description',
                'fit_score' => 88,
                'fit_summary' => 'New summary',
            ])
            ->assertOk()
            ->assertExactJson(['success' => true, 'message' => 'Application details updated.']);

        $application->refresh();

        $this->assertSame('New Company', $application->company_name);
        $this->assertSame('New Position', $application->position);
        $this->assertSame('New City', $application->location);
        $this->assertSame('New description', $application->job_description);
        $this->assertSame(88, $application->fit_score);
        $this->assertSame('New summary', $application->fit_summary);
        $this->assertSame(ApplicationStatus::Draft, $application->status);

        $after = TargetedResume::query()->findOrFail($document->id);

        $this->assertSame($document->title, $after->title);
        $this->assertSame($document->tailored_data, $after->tailored_data);

        $context = AiConversation::query()->findOrFail($application->ai_conversation_id)->context;

        foreach (['company_name', 'position', 'job_title', 'location', 'job_description', 'fit_score', 'fit_summary'] as $jobKey) {
            $this->assertArrayNotHasKey($jobKey, $context, "Job data leaked into the session context as {$jobKey}.");
        }

        $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.show', $application))
            ->assertInertia(fn (Assert $page) => $page
                ->where('application.company_name', 'New Company')
                ->where('application.position', 'New Position')
                ->where('application.location', 'New City')
                ->where('application.job_description', 'New description')
                ->where('application.fit_score', 88)
                ->where('application.fit_summary', 'New summary')
            );
    }

    public function test_editing_job_details_with_explicit_nulls_clears_location_and_fit(): void
    {
        $application = Application::factory()->create([
            'company_name' => 'Kept Company',
            'position' => 'Kept Position',
            'location' => 'Old Town',
            'job_description' => 'Kept description',
            'fit_score' => 40,
            'fit_summary' => 'Old summary',
        ]);

        $this->actingAs($this->admin)
            ->putJson(route('admin.resume.applications.update', $application), [
                'location' => null,
                'fit_score' => null,
                'fit_summary' => null,
            ])
            ->assertOk();

        $application->refresh();

        $this->assertNull($application->location);
        $this->assertNull($application->fit_score);
        $this->assertNull($application->fit_summary);
        $this->assertSame('Kept Company', $application->company_name);
        $this->assertSame('Kept Position', $application->position);
        $this->assertSame('Kept description', $application->job_description);
    }

    public function test_editing_job_details_leaves_omitted_fields_alone(): void
    {
        $application = Application::factory()->create([
            'company_name' => 'Kept Company',
            'position' => 'Kept Position',
            'location' => 'Kept Town',
            'job_description' => 'Kept description',
            'fit_score' => 40,
            'fit_summary' => 'Kept summary',
        ]);

        $this->actingAs($this->admin)
            ->putJson(route('admin.resume.applications.update', $application), ['company_name' => 'Only This Changed'])
            ->assertOk();

        $application->refresh();

        $this->assertSame('Only This Changed', $application->company_name);
        $this->assertSame('Kept Position', $application->position);
        $this->assertSame('Kept Town', $application->location);
        $this->assertSame('Kept description', $application->job_description);
        $this->assertSame(40, $application->fit_score);
        $this->assertSame('Kept summary', $application->fit_summary);
    }

    public function test_editing_job_details_keeps_the_company_and_position_when_they_are_blanked(): void
    {
        $application = Application::factory()->create(['company_name' => 'Kept Company', 'position' => 'Kept Position']);

        $this->actingAs($this->admin)
            ->putJson(route('admin.resume.applications.update', $application), ['company_name' => null, 'job_title' => ''])
            ->assertOk();

        $this->assertSame('Kept Company', $application->fresh()->company_name);
        $this->assertSame('Kept Position', $application->fresh()->position);
    }

    // ---------------------------------------------------------------------
    // Requirement: Deleting an application
    // ---------------------------------------------------------------------

    public function test_deleted_application(): void
    {
        $application = Application::factory()
            ->withTargetedResume()
            ->withConversation()
            ->applied(Carbon::parse('2001-03-10 09:00:00'))
            ->create(['company_name' => 'Deletezq Co']);
        $period = ['from' => '2001-03-01', 'to' => '2001-03-31'];
        $this->assertSame(1, ApplicationStatusUpdate::query()->whereBetween('occurred_at', ['2001-03-01', '2001-04-01'])->count());

        $this->actingAs($this->admin)
            ->get(route('admin.resume.metrics', $period))
            ->assertInertia(fn (Assert $page) => $page->where('kpis.totalApplied', 1)->has('timeline', 1));
        $this->actingAs($this->admin)
            ->get(route('admin.resume.targeted.index', ['search' => 'Deletezq']))
            ->assertInertia(fn (Assert $page) => $page->has('targetedResumes', 1));

        $this->actingAs($this->admin)
            ->delete(route('admin.resume.applications.destroy', $application))
            ->assertRedirect(route('admin.resume.applications.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('applications', ['id' => $application->id]);
        $this->assertNotNull(Application::withTrashed()->find($application->id));
        $this->assertNull(AiConversation::query()->find($application->ai_conversation_id));
        $this->assertNotNull(AiConversation::withTrashed()->find($application->ai_conversation_id)?->deleted_at);
        $this->assertNotNull(TargetedResume::query()->find($application->targeted_resume_id));

        $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.index', ['search' => 'Deletezq']))
            ->assertInertia(fn (Assert $page) => $page->where('applications', []));
        $this->actingAs($this->admin)
            ->get(route('admin.resume.targeted.index', ['search' => 'Deletezq']))
            ->assertInertia(fn (Assert $page) => $page->where('targetedResumes', []));
        $this->actingAs($this->admin)
            ->get(route('admin.resume.metrics', $period))
            ->assertInertia(fn (Assert $page) => $page->where('kpis.totalApplied', 0)->where('timeline', []));

        $this->actingAs($this->admin)->get(route('admin.resume.applications.show', $application))->assertNotFound();
        $this->actingAs($this->admin)->get(route('admin.resume.targeted.edit', $application->targeted_resume_id))->assertNotFound();
    }

    public function test_a_deleted_application_refuses_every_further_change(): void
    {
        $application = Application::factory()->withConversation()->create();
        $application->delete();

        $this->actingAs($this->admin)->putJson(route('admin.resume.applications.update', $application), ['company_name' => 'X'])->assertNotFound();
        $this->actingAs($this->admin)->postJson(route('admin.resume.applications.apply', $application))->assertNotFound();
        $this->actingAs($this->admin)->postJson(route('admin.resume.applications.pass', $application))->assertNotFound();
        $this->actingAs($this->admin)->postJson(route('admin.resume.applications.analysis', $application))->assertNotFound();
        $this->actingAs($this->admin)->postJson(route('admin.resume.applications.status-updates.store', $application), ['status' => 'applied'])->assertNotFound();
        $this->actingAs($this->admin)->delete(route('admin.resume.applications.destroy', $application))->assertNotFound();

        $this->assertSame(ApplicationStatus::Draft, Application::withTrashed()->findOrFail($application->id)->status);
    }

    public function test_deleting_an_unknown_application_is_not_found(): void
    {
        $this->actingAs($this->admin)->delete('/admin/resume/applications/999999999')->assertNotFound();
        $this->actingAs($this->admin)->get('/admin/resume/applications/999999999')->assertNotFound();
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function currentResumeVersion(): ResumeVersion
    {
        ResumeVersion::query()->update(['is_current' => false]);

        return ResumeVersion::factory()->create(['is_current' => true]);
    }

    /**
     * @return array{0: AiSystem, 1: AiSystem}
     */
    private function separateFeatureDefaults(): array
    {
        $resumeSystem = AiSystem::factory()->create();
        $coverLetterSystem = AiSystem::factory()->create();
        AiSystemFeatureDefault::create(['ai_system_id' => $resumeSystem->id, 'feature' => 'targeted-resume']);
        AiSystemFeatureDefault::create(['ai_system_id' => $coverLetterSystem->id, 'feature' => 'cover-letter']);

        return [$resumeSystem, $coverLetterSystem];
    }
}
