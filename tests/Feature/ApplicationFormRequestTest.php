<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Models\AiConversation;
use App\Models\Application;
use App\Models\ApplicationStatusUpdate;
use App\Models\ResumeVersion;
use App\Models\TargetedResume;
use App\Models\User;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Jvjvjv\CodeTalker\Models\AiSystem;
use Jvjvjv\CodeTalker\Models\AiSystemFeatureDefault;
use Jvjvjv\CodeTalker\Services\LaravelAi\AgentFactory;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Each application Form Request, driven through its real endpoint: one data
 * provider of invalid input per request, asserting the error key (and the
 * custom message where the request defines one) and that nothing was
 * written. The finalize and chat requests are covered in
 * ApplicationDiscussionTest; the metrics request in ApplicationMetricsHttpTest.
 */
class ApplicationFormRequestTest extends TestCase
{
    use DatabaseTransactions;

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

    // ---------------------------------------------------------------------
    // StoreApplicationRequest — POST applications
    // ---------------------------------------------------------------------

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string, 2?: string}>
     */
    public static function invalidStoreProvider(): array
    {
        $long = str_repeat('a', 256);

        return [
            'no intent' => [['intent' => null], 'intent', 'Choose whether to begin an analysis or record an application.'],
            'unknown intent' => [['intent' => 'draft'], 'intent', 'Choose whether to begin an analysis or record an application.'],
            'job description that is not text' => [['job_description' => ['Build things.']], 'job_description'],
            'over-long job title' => [['job_title' => $long], 'job_title'],
            'over-long company name' => [['company_name' => $long], 'company_name'],
            'over-long location' => [['job_location' => $long], 'job_location'],
            'unknown job url' => [['job_url_id' => '00000000-0000-0000-0000-000000000000'], 'job_url_id'],
            'job url that is not a string' => [['job_url_id' => 12], 'job_url_id'],
            'unknown resume version' => [['resume_version_id' => 999999999], 'resume_version_id', 'That resume version no longer exists.'],
            'resume version that is not a number' => [['resume_version_id' => 'latest'], 'resume_version_id'],
            'applied date that is not a date' => [['occurred_at' => 'not-a-date'], 'occurred_at', 'The applied date is not a valid date.'],
            'analyze without an ai system' => [['intent' => 'analyze'], 'ai_system_id', 'Choose an AI system to run the analysis.'],
            'analyze with an unknown ai system' => [['intent' => 'analyze', 'ai_system_id' => 999999999], 'ai_system_id', 'That AI system no longer exists.'],
            'analyze with an ai system that is not a number' => [['intent' => 'analyze', 'ai_system_id' => 'claude'], 'ai_system_id'],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidStoreProvider')]
    public function test_store_application_request_rejects(array $overrides, string $errorKey, ?string $message = null): void
    {
        ResumeVersion::factory()->create(['is_current' => true]);
        $applications = Application::withTrashed()->count();
        $conversations = AiConversation::withTrashed()->count();

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.store'), [
                'intent' => 'applied',
                'job_description' => 'Build things.',
                'company_name' => 'Formzq Co',
                ...$overrides,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors([$errorKey]);

        if ($message !== null) {
            $response->assertJsonPath("errors.{$errorKey}.0", $message);
        }

        $this->assertSame($applications, Application::withTrashed()->count());
        $this->assertSame($conversations, AiConversation::withTrashed()->count());
    }

    public function test_store_application_request_accepts_strings_at_their_length_limit(): void
    {
        ResumeVersion::factory()->create(['is_current' => true]);
        $limit = str_repeat('a', 191);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.store'), [
                'intent' => 'applied',
                'job_description' => 'Build things.',
                'job_title' => $limit,
                'company_name' => $limit,
                'job_location' => $limit,
            ])
            ->assertOk();

        $application = Application::query()->findOrFail($response->json('application_id'));

        $this->assertSame($limit, $application->position);
        $this->assertSame($limit, $application->company_name);
        $this->assertSame($limit, $application->location);
    }

    public function test_update_application_request_accepts_strings_at_their_length_limit(): void
    {
        $application = Application::factory()->create();
        $limit = str_repeat('a', 191);

        $this->actingAs($this->admin)
            ->putJson(route('admin.resume.applications.update', $application), [
                'company_name' => $limit,
                'job_title' => $limit,
                'location' => $limit,
            ])
            ->assertOk();

        $application->refresh();

        $this->assertSame($limit, $application->company_name);
        $this->assertSame($limit, $application->position);
        $this->assertSame($limit, $application->location);
    }

    // ---------------------------------------------------------------------
    // UpdateApplicationRequest — PUT applications/{application}
    // ---------------------------------------------------------------------

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string, 2?: string}>
     */
    public static function invalidUpdateProvider(): array
    {
        $long = str_repeat('a', 256);
        $fitMessage = 'The fit score must be a whole number from 1 to 100.';

        return [
            'fit score of zero' => [['fit_score' => 0], 'fit_score', $fitMessage],
            'negative fit score' => [['fit_score' => -5], 'fit_score', $fitMessage],
            'fit score above 100' => [['fit_score' => 101], 'fit_score', $fitMessage],
            'fractional fit score' => [['fit_score' => 72.5], 'fit_score', $fitMessage],
            'fit score that is not a number' => [['fit_score' => 'high'], 'fit_score', $fitMessage],
            'over-long session title' => [['title' => $long], 'title'],
            'over-long company name' => [['company_name' => $long], 'company_name'],
            'over-long job title' => [['job_title' => $long], 'job_title'],
            'over-long location' => [['location' => $long], 'location'],
            'null job description' => [['job_description' => null], 'job_description', 'A job description cannot be blank.'],
            'blank job description' => [['job_description' => ''], 'job_description', 'A job description cannot be blank.'],
            'whitespace job description' => [['job_description' => '   '], 'job_description', 'A job description cannot be blank.'],
            'job description that is not text' => [['job_description' => ['Build things.']], 'job_description'],
            'fit summary that is not text' => [['fit_summary' => ['Good']], 'fit_summary'],
            'company name that is not text' => [['company_name' => ['Acme']], 'company_name'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('invalidUpdateProvider')]
    public function test_update_application_request_rejects(array $payload, string $errorKey, ?string $message = null): void
    {
        $application = Application::factory()->withConversation()->create([
            'company_name' => 'Kept Company',
            'position' => 'Kept Position',
            'location' => 'Kept Town',
            'job_description' => 'Kept description',
            'fit_score' => 40,
            'fit_summary' => 'Kept summary',
        ]);
        $title = $application->conversation->title;

        $response = $this->actingAs($this->admin)
            ->putJson(route('admin.resume.applications.update', $application), ['company_name' => 'Changed Company', ...$payload])
            ->assertStatus(422)
            ->assertJsonValidationErrors([$errorKey]);

        if ($message !== null) {
            $response->assertJsonPath("errors.{$errorKey}.0", $message);
        }

        $application->refresh();

        $this->assertSame('Kept Company', $application->company_name);
        $this->assertSame('Kept Position', $application->position);
        $this->assertSame('Kept Town', $application->location);
        $this->assertSame('Kept description', $application->job_description);
        $this->assertSame(40, $application->fit_score);
        $this->assertSame('Kept summary', $application->fit_summary);
        $this->assertSame($title, $application->conversation->fresh()->title);
    }

    /**
     * @return array<string, array{0: int}>
     */
    public static function fitScoreBoundProvider(): array
    {
        return ['lowest' => [1], 'highest' => [100]];
    }

    #[DataProvider('fitScoreBoundProvider')]
    public function test_update_application_request_accepts_the_fit_score_bounds(int $fitScore): void
    {
        $application = Application::factory()->create(['fit_score' => 40]);

        $this->actingAs($this->admin)
            ->putJson(route('admin.resume.applications.update', $application), ['fit_score' => $fitScore])
            ->assertOk();

        $this->assertSame($fitScore, $application->fresh()->fit_score);
    }

    public function test_update_application_request_ignores_fields_it_does_not_define(): void
    {
        $application = Application::factory()->create(['status' => ApplicationStatus::Draft]);
        $versionId = $application->resume_version_id;
        $other = ResumeVersion::factory()->create();

        $this->actingAs($this->admin)
            ->putJson(route('admin.resume.applications.update', $application), [
                'status' => 'hired',
                'resume_version_id' => $other->id,
                'targeted_resume_id' => 12345,
                'ai_conversation_id' => 12345,
                'deleted_at' => '2026-01-01',
            ])
            ->assertOk();

        $application->refresh();

        $this->assertSame(ApplicationStatus::Draft, $application->status);
        $this->assertSame($versionId, $application->resume_version_id);
        $this->assertNull($application->targeted_resume_id);
        $this->assertNull($application->ai_conversation_id);
        $this->assertNull($application->deleted_at);
    }

    // ---------------------------------------------------------------------
    // ApplyApplicationRequest — POST applications/{application}/apply
    // ---------------------------------------------------------------------

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string, 2?: string}>
     */
    public static function invalidApplyProvider(): array
    {
        return [
            'unknown resume version' => [['resume_version_id' => 999999999], 'resume_version_id', 'That resume version no longer exists.'],
            'resume version that is not a number' => [['resume_version_id' => 'current'], 'resume_version_id'],
            'fractional resume version' => [['resume_version_id' => 1.5], 'resume_version_id'],
            'applied date that is not a date' => [['occurred_at' => 'last tuesday-ish'], 'occurred_at', 'The applied date is not a valid date.'],
            'applied date that is a list' => [['occurred_at' => ['2026-01-01']], 'occurred_at'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('invalidApplyProvider')]
    public function test_apply_application_request_rejects(array $payload, string $errorKey, ?string $message = null): void
    {
        $application = Application::factory()->create();
        $versionId = $application->resume_version_id;

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.apply', $application), $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors([$errorKey]);

        if ($message !== null) {
            $response->assertJsonPath("errors.{$errorKey}.0", $message);
        }

        $this->assertSame(ApplicationStatus::Draft, $application->fresh()->status);
        $this->assertSame($versionId, $application->fresh()->resume_version_id);
        $this->assertSame(0, $application->statusUpdates()->count());
    }

    // ---------------------------------------------------------------------
    // BeginApplicationAnalysisRequest — POST applications/{application}/analysis
    // ---------------------------------------------------------------------

    /**
     * @return array<string, array{0: array<string, mixed>, 1?: string}>
     */
    public static function invalidAnalysisProvider(): array
    {
        return [
            'unknown ai system' => [['ai_system_id' => 999999999], 'That AI system no longer exists.'],
            'ai system that is not a number' => [['ai_system_id' => 'claude']],
            'ai system that is a list' => [['ai_system_id' => [1]]],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('invalidAnalysisProvider')]
    public function test_begin_application_analysis_request_rejects(array $payload, ?string $message = null): void
    {
        AiSystem::factory()->create();
        $application = Application::factory()->create();
        $conversations = AiConversation::withTrashed()->count();

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.analysis', $application), $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ai_system_id']);

        if ($message !== null) {
            $response->assertJsonPath('errors.ai_system_id.0', $message);
        }

        $this->assertNull($application->fresh()->ai_conversation_id);
        $this->assertSame($conversations, AiConversation::withTrashed()->count());
    }

    // ---------------------------------------------------------------------
    // StoreApplicationStatusUpdateRequest — POST …/status-updates
    // ---------------------------------------------------------------------

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string, 2?: string}>
     */
    public static function invalidStatusUpdateStoreProvider(): array
    {
        $pipelineMessage = 'Status history entries must use a pipeline status.';

        return [
            'no status' => [[], 'status', 'Choose a status for this entry.'],
            'null status' => [['status' => null], 'status', 'Choose a status for this entry.'],
            'unknown status' => [['status' => 'dancing'], 'status', $pipelineMessage],
            'draft is not a pipeline status' => [['status' => 'draft'], 'status', $pipelineMessage],
            'passed is not a pipeline status' => [['status' => 'passed'], 'status', $pipelineMessage],
            'ghosted is a display, not a status' => [['status' => 'ghosted'], 'status', $pipelineMessage],
            'finalized is no longer a status' => [['status' => 'finalized'], 'status', $pipelineMessage],
            'status in the wrong case' => [['status' => 'Interviewing'], 'status', $pipelineMessage],
            'status that is a list' => [['status' => ['interviewing']], 'status'],
            'over-long notes' => [['status' => 'interviewing', 'notes' => str_repeat('n', 1001)], 'notes', 'Notes are limited to 1,000 characters.'],
            'notes that are not text' => [['status' => 'interviewing', 'notes' => ['a note']], 'notes'],
            'date that is not a date' => [['status' => 'interviewing', 'occurred_at' => 'not-a-date'], 'occurred_at', 'The date is not a valid date.'],
            'impossible date' => [['status' => 'interviewing', 'occurred_at' => '2026-02-31'], 'occurred_at', 'The date is not a valid date.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('invalidStatusUpdateStoreProvider')]
    public function test_store_application_status_update_request_rejects(array $payload, string $errorKey, ?string $message = null): void
    {
        $application = Application::factory()->applied()->create();

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.status-updates.store', $application), $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors([$errorKey]);

        if ($message !== null) {
            $response->assertJsonPath("errors.{$errorKey}.0", $message);
        }

        $this->assertSame(ApplicationStatus::Applied, $application->fresh()->status);
        $this->assertSame(1, $application->statusUpdates()->count());
    }

    public function test_store_application_status_update_request_accepts_notes_at_the_limit_and_every_pipeline_status(): void
    {
        $notes = str_repeat('n', 1000);

        foreach (ApplicationStatus::pipeline() as $status) {
            $application = Application::factory()->applied()->create();

            $this->actingAs($this->admin)
                ->postJson(route('admin.resume.applications.status-updates.store', $application), [
                    'status' => $status->value,
                    'notes' => $notes,
                    'occurred_at' => '2026-06-12',
                ])
                ->assertOk()
                ->assertJsonPath('status', $status->value);

            $this->assertSame($notes, $application->statusUpdates()->reorder()->latest('id')->firstOrFail()->notes);
        }
    }

    // ---------------------------------------------------------------------
    // UpdateApplicationStatusUpdateRequest — PUT …/status-updates/{statusUpdate}
    // ---------------------------------------------------------------------

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string, 2?: string}>
     */
    public static function invalidStatusUpdateUpdateProvider(): array
    {
        return [
            'no date' => [['notes' => 'No date'], 'occurred_at', 'A status entry needs a date.'],
            'null date' => [['occurred_at' => null], 'occurred_at', 'A status entry needs a date.'],
            'blank date' => [['occurred_at' => ''], 'occurred_at', 'A status entry needs a date.'],
            'date that is not a date' => [['occurred_at' => 'not-a-date'], 'occurred_at', 'The date is not a valid date.'],
            'impossible date' => [['occurred_at' => '2026-02-31'], 'occurred_at', 'The date is not a valid date.'],
            'over-long notes' => [['occurred_at' => '2026-06-12', 'notes' => str_repeat('n', 1001)], 'notes', 'Notes are limited to 1,000 characters.'],
            'notes that are not text' => [['occurred_at' => '2026-06-12', 'notes' => ['a note']], 'notes'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('invalidStatusUpdateUpdateProvider')]
    public function test_update_application_status_update_request_rejects(array $payload, string $errorKey, ?string $message = null): void
    {
        $application = Application::factory()->create(['status' => ApplicationStatus::Applied]);
        $entry = ApplicationStatusUpdate::factory()->create([
            'application_id' => $application->id,
            'status' => ApplicationStatus::Applied,
            'notes' => 'Original note',
            'occurred_at' => '2026-03-01 09:00:00',
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson(route('admin.resume.applications.status-updates.update', [$application, $entry]), $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors([$errorKey]);

        if ($message !== null) {
            $response->assertJsonPath("errors.{$errorKey}.0", $message);
        }

        $entry->refresh();

        $this->assertSame('Original note', $entry->notes);
        $this->assertSame('2026-03-01 09:00:00', $entry->occurred_at->toDateTimeString());
    }

    public function test_update_application_status_update_request_cannot_change_an_entrys_status(): void
    {
        $application = Application::factory()->applied()->create();
        $entry = $application->statusUpdates()->sole();

        $this->actingAs($this->admin)
            ->putJson(route('admin.resume.applications.status-updates.update', [$application, $entry]), [
                'status' => 'hired',
                'notes' => 'Edited',
                'occurred_at' => '2026-06-12',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'applied')
            ->assertJsonPath('status_updates.0.status', 'applied');

        $this->assertSame(ApplicationStatus::Applied, $entry->fresh()->status);
        $this->assertSame('Edited', $entry->fresh()->notes);
        $this->assertSame(ApplicationStatus::Applied, $application->fresh()->status);
    }

    public function test_status_update_routes_are_not_found_for_an_unknown_entry(): void
    {
        $application = Application::factory()->applied()->create();
        $base = "/api/admin/resume/applications/{$application->id}/status-updates";

        $this->actingAs($this->admin)->putJson("{$base}/999999999", ['occurred_at' => '2026-06-12'])->assertNotFound();
        $this->actingAs($this->admin)->deleteJson("{$base}/999999999")->assertNotFound();
        $this->actingAs($this->admin)->deleteJson("{$base}/not-a-number")->assertNotFound();
        $this->assertSame(1, $application->statusUpdates()->count());
    }

    // ---------------------------------------------------------------------
    // UpdateTargetedResumeMarkdownRequest — PUT targeted-resume/{targetedResume}
    // ---------------------------------------------------------------------

    /**
     * @return array<string, array{0: array<string, mixed>, 1?: string}>
     */
    public static function invalidMarkdownProvider(): array
    {
        return [
            'null markdown' => [['markdown' => null], 'The resume content cannot be empty.'],
            'blank markdown' => [['markdown' => ''], 'The resume content cannot be empty.'],
            'whitespace markdown' => [['markdown' => "  \n  "], 'The resume content cannot be empty.'],
            'markdown that is not text' => [['markdown' => ['# Summary']]],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('invalidMarkdownProvider')]
    public function test_update_targeted_resume_markdown_request_rejects(array $payload, ?string $message = null): void
    {
        $application = Application::factory()->withTargetedResume()->create();
        $before = TargetedResume::query()->findOrFail($application->targeted_resume_id)->tailored_data;

        $response = $this->actingAs($this->admin)
            ->putJson(route('admin.resume.targeted-resume.update-markdown', $application->targeted_resume_id), $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['markdown']);

        if ($message !== null) {
            $response->assertJsonPath('errors.markdown.0', $message);
        }

        $this->assertSame($before, TargetedResume::query()->findOrFail($application->targeted_resume_id)->tailored_data);
    }

    // ---------------------------------------------------------------------
    // StoreCoverLetterRequest — the application link
    // ---------------------------------------------------------------------

    public function test_store_cover_letter_request_rejects_an_application_id_that_is_not_a_number(): void
    {
        $version = ResumeVersion::factory()->create();
        Permission::firstOrCreate(['name' => 'manage-unauthenticated-viewers']);
        $this->admin->givePermissionTo('manage-unauthenticated-viewers');

        $this->actingAs($this->admin->fresh())
            ->postJson(route('admin.cover-letters.store'), [
                'resume_version_id' => $version->id,
                'application_id' => 'acme',
                'company_name' => 'Formzq Letter Co',
                'position' => 'Engineer',
                'date' => '2026-03-01',
                'greeting' => 'Hello,',
                'message_body' => 'Body.',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['application_id']);

        $this->assertDatabaseMissing('cover_letters', ['company_name' => 'Formzq Letter Co']);
    }
}
