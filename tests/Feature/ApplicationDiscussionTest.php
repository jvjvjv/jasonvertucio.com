<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Models\AiConversation;
use App\Models\Application;
use App\Models\ApplicationStatusUpdate;
use App\Models\CoverLetter;
use App\Models\JobUrl;
use App\Models\ResumeVersion;
use App\Models\TargetedResume;
use App\Models\User;
use App\Services\CoverLetterDocumentService;
use App\Support\AnalysisSystemGuard;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Jvjvjv\CodeTalker\Enums\AiConversationStatus;
use Jvjvjv\CodeTalker\Models\AiConversationMessage;
use Jvjvjv\CodeTalker\Models\AiSystem;
use Jvjvjv\CodeTalker\Models\AiSystemFeatureDefault;
use Jvjvjv\CodeTalker\Services\LaravelAi\AgentFactory;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * HTTP-level scenarios for the Application Discussion page and the session
 * endpoints behind it: beginning analysis later, finalizing a targeted
 * resume and finalizing a cover letter. No test opens a chat stream; the
 * agent factory is replaced by a mock that refuses to build an agent.
 */
class ApplicationDiscussionTest extends TestCase
{
    use DatabaseTransactions;

    private const string FIRST_RESUME = "Title: Staff Platform Engineer\n\n# Summary\nFirst draft";

    private const string SECOND_RESUME = "Title: Principal Platform Engineer\n\n# Summary\nSecond draft";

    private const string FIRST_LETTER = "Dear Acme hiring team,\n\nI build reliable Laravel applications.\n\nKind regards,";

    private const string SECOND_LETTER = "Dear Acme hiring team,\n\nI lead teams that build reliable Laravel applications.\n\nBest regards,";

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
    // Requirement: Application Discussion page
    // ---------------------------------------------------------------------

    public function test_application_with_an_ai_session(): void
    {
        $system = AiSystem::factory()->create(['name' => 'Discusszq System']);
        $application = Application::factory()->create(['company_name' => 'Discusszq Co']);
        $conversation = AiConversation::factory()->create([
            'ai_system_id' => $system->id,
            'feature' => 'targeted-resume',
            'title' => 'Targeted Resume: Staff Engineer',
            'status' => AiConversationStatus::Active,
            'context' => ['step' => 'analysis', 'auto_start_pending' => true],
        ]);
        $application->update(['ai_conversation_id' => $conversation->id]);

        $this->message($conversation, 'system', 'System prompt — never shown');
        $this->message($conversation, 'user', 'Please begin the analysis');
        $this->message($conversation, 'assistant', 'Here is the fit analysis.');
        $this->message($conversation, 'user', 'I edited it by hand.', ['origin' => 'manual_edit']);

        $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.show', $application))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('resume/applications/Show', false)
                ->where('application.id', $application->id)
                ->where('conversation.id', $conversation->id)
                ->where('conversation.status', 'active')
                ->where('conversation.title', 'Targeted Resume: Staff Engineer')
                ->where('conversation.context', ['step' => 'analysis', 'auto_start_pending' => true])
                ->where('conversation.ai_system_id', $system->id)
                ->where('conversation.ai_system_name', 'Discusszq System')
                ->has('conversation.usage', fn (Assert $usage) => $usage
                    ->hasAll(['input_tokens', 'output_tokens', 'total_tokens', 'cost_usd', 'synced_at'])
                )
                ->has('messages', 3)
                ->where('messages.0.role', 'user')
                ->where('messages.0.content', 'Please begin the analysis')
                ->where('messages.1.role', 'assistant')
                ->where('messages.1.content', 'Here is the fit analysis.')
                ->where('messages.2.metadata.origin', 'manual_edit')
                ->has('messages.0.created_at')
                ->where('messages', fn ($messages): bool => collect($messages)->pluck('role')->doesntContain('system')
                    && collect($messages)->pluck('content')->doesntContain('System prompt — never shown'))
            );
    }

    public function test_analysis_starts_automatically_only_until_the_agent_has_answered(): void
    {
        $pending = Application::factory()->withConversation()->create();
        $pending->conversation->update(['context' => ['step' => 'analysis', 'auto_start_pending' => true]]);
        $this->message($pending->conversation, 'system', 'System prompt');
        $this->message($pending->conversation, 'user', 'Please begin the analysis');

        $answered = Application::factory()->withConversation()->create();
        $answered->conversation->update(['context' => ['step' => 'analysis', 'auto_start_pending' => true]]);
        $this->message($answered->conversation, 'user', 'Please begin the analysis');
        $this->message($answered->conversation, 'assistant', 'Done.');

        $notPending = Application::factory()->withConversation()->create();
        $notPending->conversation->update(['context' => ['step' => 'analysis']]);
        $this->message($notPending->conversation, 'user', 'Please begin the analysis');

        foreach ([[$pending, true], [$answered, false], [$notPending, false]] as [$application, $expected]) {
            $this->actingAs($this->admin)
                ->get(route('admin.resume.applications.show', $application))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->where('shouldAutoStart', $expected));
        }
    }

    public function test_application_without_an_ai_session(): void
    {
        $jobUrl = JobUrl::factory()->create(['url' => 'https://jobs.example.com/discusszq']);
        $version = ResumeVersion::factory()->create();
        $application = Application::factory()->create([
            'resume_version_id' => $version->id,
            'job_url_id' => $jobUrl->id,
            'company_name' => 'Discusszq Applied Co',
            'position' => 'Staff Engineer',
            'location' => 'Remote',
            'job_description' => 'Build things.',
            'fit_score' => 72,
            'fit_summary' => 'Good fit.',
            'status' => ApplicationStatus::Interviewing,
        ]);
        $applied = $this->entry($application, ApplicationStatus::Applied, '2026-03-01 09:00:00', 'Via referral');
        $interviewing = $this->entry($application, ApplicationStatus::Interviewing, '2026-03-09 15:00:00', 'Phone screen');
        $letter = $this->coverLetter($application);

        $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.show', $application))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('resume/applications/Show', false)
                ->where('conversation', null)
                ->where('messages', [])
                ->where('shouldAutoStart', false)
                ->where('application.id', $application->id)
                ->where('application.status', 'interviewing')
                ->where('application.company_name', 'Discusszq Applied Co')
                ->where('application.position', 'Staff Engineer')
                ->where('application.location', 'Remote')
                ->where('application.job_description', 'Build things.')
                ->where('application.job_url', 'https://jobs.example.com/discusszq')
                ->where('application.fit_score', 72)
                ->where('application.fit_summary', 'Good fit.')
                ->where('application.has_applied', true)
                ->where('application.allowed_next_statuses', ['interviewed', 'rejected'])
                ->where('application.status_updates', [
                    ['id' => $applied->id, 'status' => 'applied', 'notes' => 'Via referral', 'occurred_at' => Carbon::parse('2026-03-01 09:00:00')->toIso8601String()],
                    ['id' => $interviewing->id, 'status' => 'interviewing', 'notes' => 'Phone screen', 'occurred_at' => Carbon::parse('2026-03-09 15:00:00')->toIso8601String()],
                ])
                ->where('coverLetter.id', $letter->id)
                ->where('coverLetter.company_name', 'Discusszq Applied Co')
                ->has('systems')
                ->has('defaultSystemId')
            );
    }

    public function test_application_whose_session_was_deleted_offers_begin_analysis_again(): void
    {
        $application = Application::factory()->withConversation()->create();
        $this->message($application->conversation, 'user', 'Earlier turn');
        $application->conversation->delete();

        $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.show', $application))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('conversation', null)
                ->where('messages', [])
                ->where('shouldAutoStart', false)
            );
    }

    public function test_resume_card_with_a_targeted_resume(): void
    {
        $version = ResumeVersion::factory()->create();
        $application = Application::factory()->withTargetedResume()->create(['resume_version_id' => $version->id]);
        $targetedResume = $application->targetedResume;

        $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.show', $application))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('targetedResume', [
                    'id' => $targetedResume->id,
                    'title' => $targetedResume->title,
                    'tailored_content' => data_get($targetedResume->tailored_data, 'markdown'),
                    'docx_path' => false,
                    'pdf_path' => false,
                    'resume_version' => $version->version,
                ])
                ->where('application.resume_version', ['id' => $version->id, 'version' => $version->version])
            );
    }

    public function test_resume_card_without_a_targeted_resume(): void
    {
        $version = ResumeVersion::factory()->create();
        $application = Application::factory()->withConversation()->create(['resume_version_id' => $version->id]);

        $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.show', $application))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('targetedResume', null)
                ->where('application.resume_version', ['id' => $version->id, 'version' => $version->version])
                ->where('currentResumeVersionId', ResumeVersion::current()->value('id'))
                ->where('resumeVersions', fn ($versions): bool => collect($versions)->pluck('id')->contains($version->id))
            );
    }

    public function test_cover_letter_card_is_absent_until_a_letter_exists(): void
    {
        $application = Application::factory()->withConversation()->create();
        $this->coverLetter(Application::factory()->create());

        $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.show', $application))
            ->assertInertia(fn (Assert $page) => $page->where('coverLetter', null));

        $letter = $this->coverLetter($application);

        $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.show', $application))
            ->assertInertia(fn (Assert $page) => $page->where('coverLetter', [
                'id' => $letter->id,
                'company_name' => $application->company_name,
                'position' => $application->position,
                'docx_path' => false,
                'pdf_path' => false,
            ]));
    }

    // ---------------------------------------------------------------------
    // Scenario: Beginning analysis later
    // ---------------------------------------------------------------------

    public function test_beginning_analysis_later(): void
    {
        $system = AiSystem::factory()->create();
        $application = Application::factory()->create([
            'company_name' => 'Laterzq Co',
            'position' => 'Staff Engineer',
            'job_description' => 'Build things later.',
            'status' => ApplicationStatus::Interviewing,
        ]);
        $this->entry($application, ApplicationStatus::Applied, '2026-03-01 09:00:00', 'Via referral');
        $this->entry($application, ApplicationStatus::Interviewing, '2026-03-09 15:00:00', 'Phone screen');
        $history = $this->historySnapshot($application);
        $applications = Application::withTrashed()->count();

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.analysis', $application), ['ai_system_id' => $system->id])
            ->assertOk();

        $application->refresh();

        $response->assertExactJson([
            'success' => true,
            'conversation_id' => $application->ai_conversation_id,
            'redirect' => route('admin.resume.applications.show', $application),
        ]);

        $this->assertSame($applications, Application::withTrashed()->count());
        $this->assertNotNull($application->ai_conversation_id);
        $this->assertSame(ApplicationStatus::Interviewing, $application->status);
        $this->assertSame($history, $this->historySnapshot($application));
        $this->assertNull($application->targeted_resume_id);

        $conversation = AiConversation::query()->findOrFail($application->ai_conversation_id);

        $this->assertSame($system->id, $conversation->ai_system_id);
        $this->assertSame($this->admin->id, $conversation->user_id);
        $this->assertSame(['step' => 'analysis', 'auto_start_pending' => true], $conversation->context);

        $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.show', $application))
            ->assertInertia(fn (Assert $page) => $page
                ->where('conversation.id', $conversation->id)
                ->where('shouldAutoStart', true)
                ->where('application.status', 'interviewing')
                ->has('application.status_updates', 2)
                ->has('messages', 1)
                ->where('messages.0.role', 'user')
                ->where('messages.0.content', fn (string $content): bool => str_contains($content, 'Build things later.') && str_contains($content, 'Laterzq Co'))
            );
    }

    public function test_beginning_analysis_later_uses_the_default_system_when_none_is_chosen(): void
    {
        $default = AiSystem::factory()->create();
        AiSystem::factory()->create();
        AiSystemFeatureDefault::create(['ai_system_id' => $default->id, 'feature' => 'targeted-resume']);
        $application = Application::factory()->applied()->create();

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.analysis', $application))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame($default->id, AiConversation::query()->findOrFail($application->fresh()->ai_conversation_id)->ai_system_id);
    }

    public function test_beginning_analysis_is_refused_when_no_system_is_chosen_and_none_is_configured(): void
    {
        $application = Application::factory()->applied()->create();

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.analysis', $application))
            ->assertStatus(422)
            ->assertJsonStructure(['message']);

        $this->assertNull($application->fresh()->ai_conversation_id);
    }

    public function test_beginning_analysis_is_refused_for_an_unknown_system(): void
    {
        $application = Application::factory()->applied()->create();

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.analysis', $application), ['ai_system_id' => 999999999])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ai_system_id']);

        $this->assertNull($application->fresh()->ai_conversation_id);
    }

    public function test_beginning_analysis_is_refused_while_the_resume_and_cover_letter_defaults_are_different_systems(): void
    {
        $resumeSystem = AiSystem::factory()->create();
        $coverLetterSystem = AiSystem::factory()->create();
        AiSystemFeatureDefault::create(['ai_system_id' => $resumeSystem->id, 'feature' => 'targeted-resume']);
        AiSystemFeatureDefault::create(['ai_system_id' => $coverLetterSystem->id, 'feature' => 'cover-letter']);
        $application = Application::factory()->applied()->create();

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.analysis', $application), ['ai_system_id' => $resumeSystem->id])
            ->assertStatus(422)
            ->assertExactJson([
                'error' => AnalysisSystemGuard::SEPARATE_MODELS_MESSAGE,
                'message' => AnalysisSystemGuard::SEPARATE_MODELS_MESSAGE,
            ]);

        $this->assertNull($application->fresh()->ai_conversation_id);
    }

    public function test_beginning_analysis_is_refused_when_the_application_already_has_a_session(): void
    {
        $application = Application::factory()->withConversation()->create();
        $conversationId = $application->ai_conversation_id;
        $conversations = AiConversation::withTrashed()->count();

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.analysis', $application), ['ai_system_id' => AiSystem::factory()->create()->id])
            ->assertStatus(409)
            ->assertJsonStructure(['message']);

        $this->assertSame($conversationId, $application->fresh()->ai_conversation_id);
        $this->assertSame($conversations, AiConversation::withTrashed()->count());
    }

    public function test_beginning_analysis_replaces_a_session_that_was_deleted(): void
    {
        $system = AiSystem::factory()->create();
        $application = Application::factory()->withConversation()->applied(Carbon::parse('2026-03-01 09:00:00'))->create();
        $deletedConversationId = $application->ai_conversation_id;
        $this->message($application->conversation, 'user', 'Earlier turn');
        $application->conversation->delete();
        $history = $this->historySnapshot($application);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.analysis', $application), ['ai_system_id' => $system->id])
            ->assertOk()
            ->assertJsonPath('success', true);

        $application->refresh();

        $this->assertNotNull($application->ai_conversation_id);
        $this->assertNotSame($deletedConversationId, $application->ai_conversation_id);
        $response->assertJsonPath('conversation_id', $application->ai_conversation_id);
        $this->assertNotNull(AiConversation::query()->find($application->ai_conversation_id));
        $this->assertNotNull(AiConversation::withTrashed()->findOrFail($deletedConversationId)->deleted_at);
        $this->assertSame(ApplicationStatus::Applied, $application->status);
        $this->assertSame($history, $this->historySnapshot($application));

        $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.show', $application))
            ->assertInertia(fn (Assert $page) => $page
                ->where('conversation.id', $application->ai_conversation_id)
                ->where('shouldAutoStart', true)
                ->where('messages', fn ($messages): bool => collect($messages)->pluck('content')->doesntContain('Earlier turn'))
            );

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.analysis', $application), ['ai_system_id' => $system->id])
            ->assertStatus(409);
    }

    // ---------------------------------------------------------------------
    // Session endpoints without a session
    // ---------------------------------------------------------------------

    /**
     * @return array<string, array{0: string, 1: array<string, mixed>}>
     */
    public static function sessionEndpointProvider(): array
    {
        return [
            'chat' => ['admin.resume.applications.chat', ['message' => 'Hello']],
            'finalize' => ['admin.resume.applications.finalize', ['tailored_content' => self::FIRST_RESUME]],
            'finalize cover letter' => ['admin.resume.applications.finalize-cover-letter', ['cover_letter_content' => self::FIRST_LETTER]],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('sessionEndpointProvider')]
    public function test_session_endpoints_answer_409_for_an_application_whose_session_was_deleted(string $routeName, array $payload): void
    {
        $this->stubCoverLetterDocuments();
        $application = Application::factory()->withConversation()->create();
        $application->conversation->delete();
        $documents = TargetedResume::query()->count();
        $letters = CoverLetter::query()->count();

        $this->actingAs($this->admin)
            ->postJson(route($routeName, $application), $payload)
            ->assertStatus(409)
            ->assertExactJson(['message' => 'This application has no AI session. Begin an analysis first.']);

        $this->assertNull($application->fresh()->targeted_resume_id);
        $this->assertSame($documents, TargetedResume::query()->count());
        $this->assertSame($letters, CoverLetter::query()->count());
    }

    #[DataProvider('sessionEndpointProvider')]
    public function test_session_endpoints_answer_409_json_even_to_a_non_json_caller(string $routeName, array $payload): void
    {
        $this->stubCoverLetterDocuments();
        $application = Application::factory()->applied()->create();

        $this->actingAs($this->admin)
            ->post(route($routeName, $application), $payload, ['Accept' => 'text/event-stream'])
            ->assertStatus(409)
            ->assertExactJson(['message' => 'This application has no AI session. Begin an analysis first.']);

        $this->assertSame(0, $application->coverLetters()->count());
    }

    public function test_chat_rejects_a_message_that_is_not_text(): void
    {
        $application = Application::factory()->withConversation()->create();
        $messages = AiConversationMessage::query()->where('ai_conversation_id', $application->ai_conversation_id)->count();

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.chat', $application), ['message' => ['not', 'text']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['message']);

        $this->assertSame($messages, AiConversationMessage::query()->where('ai_conversation_id', $application->ai_conversation_id)->count());
    }

    // ---------------------------------------------------------------------
    // Requirement: Finalizing a targeted resume attaches it to the Application
    // ---------------------------------------------------------------------

    public function test_first_finalize(): void
    {
        $application = Application::factory()->withConversation()->create(['fit_score' => 40]);
        $documents = TargetedResume::query()->count();

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.finalize', $application), [
                'tailored_content' => self::FIRST_RESUME,
                'fit_score' => 83,
            ])
            ->assertOk();

        $application->refresh();

        $response->assertExactJson([
            'success' => true,
            'targeted_resume_id' => $application->targeted_resume_id,
            'message' => 'Targeted resume saved successfully.',
        ]);

        $this->assertSame($documents + 1, TargetedResume::query()->count());
        $this->assertNotNull($application->targeted_resume_id);
        $this->assertSame(ApplicationStatus::Draft, $application->status);
        $this->assertSame(0, $application->statusUpdates()->count());
        $this->assertSame(83, $application->fit_score);

        $targetedResume = TargetedResume::query()->findOrFail($application->targeted_resume_id);

        $this->assertSame('Staff Platform Engineer', $targetedResume->title);
        $this->assertSame("# Summary\nFirst draft", data_get($targetedResume->tailored_data, 'markdown'));
        $this->assertSame($application->resume_version_id, $targetedResume->resume_version_id);

        $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.show', $application))
            ->assertInertia(fn (Assert $page) => $page
                ->where('application.status', 'draft')
                ->where('targetedResume.id', $targetedResume->id)
                ->where('targetedResume.title', 'Staff Platform Engineer')
                ->where('targetedResume.tailored_content', "# Summary\nFirst draft")
            );

        $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.index', ['uses_targeted_resume' => 'yes', 'search' => $application->company_name]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('applications', fn ($rows): bool => collect($rows)->firstWhere('id', $application->id)['targeted_resume_id'] === $targetedResume->id
                    && collect($rows)->firstWhere('id', $application->id)['status'] === 'draft')
            );
    }

    public function test_first_finalize_without_a_fit_score_keeps_the_recorded_one(): void
    {
        $application = Application::factory()->withConversation()->create(['fit_score' => 40]);

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.finalize', $application), ['tailored_content' => self::FIRST_RESUME])
            ->assertOk();

        $this->assertSame(40, $application->fresh()->fit_score);
    }

    public function test_finalize_after_applying(): void
    {
        $application = Application::factory()->withConversation()->create();

        $first = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.finalize', $application), ['tailored_content' => self::FIRST_RESUME])
            ->assertOk()
            ->json('targeted_resume_id');

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.apply', $application), ['occurred_at' => '2026-04-20'])
            ->assertOk()
            ->assertJsonPath('status', 'applied');

        $documents = TargetedResume::query()->count();
        $history = $this->historySnapshot($application);

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.finalize', $application), ['tailored_content' => self::SECOND_RESUME])
            ->assertOk()
            ->assertJsonPath('targeted_resume_id', $first);

        $application->refresh();

        $this->assertSame($first, $application->targeted_resume_id);
        $this->assertSame($documents, TargetedResume::query()->count());
        $this->assertSame(ApplicationStatus::Applied, $application->status);
        $this->assertSame($history, $this->historySnapshot($application));

        $targetedResume = TargetedResume::query()->findOrFail($first);

        $this->assertSame('Principal Platform Engineer', $targetedResume->title);
        $this->assertSame("# Summary\nSecond draft", data_get($targetedResume->tailored_data, 'markdown'));
        $this->assertSame("# Summary\nSecond draft", data_get($targetedResume->tailored_data, 'content'));
    }

    public function test_finalize_does_not_change_a_passed_application(): void
    {
        $application = Application::factory()->passed()->withConversation()->create();

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.finalize', $application), ['tailored_content' => self::FIRST_RESUME])
            ->assertOk();

        $this->assertSame(ApplicationStatus::Passed, $application->fresh()->status);
        $this->assertNotNull($application->fresh()->targeted_resume_id);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidFinalizeProvider(): array
    {
        return [
            'no content' => [[], 'tailored_content'],
            'null content' => [['tailored_content' => null], 'tailored_content'],
            'blank content' => [['tailored_content' => '   '], 'tailored_content'],
            'content that is not text' => [['tailored_content' => ['# Summary']], 'tailored_content'],
            'fit score of zero' => [['tailored_content' => self::FIRST_RESUME, 'fit_score' => 0], 'fit_score'],
            'fit score above 100' => [['tailored_content' => self::FIRST_RESUME, 'fit_score' => 101], 'fit_score'],
            'fractional fit score' => [['tailored_content' => self::FIRST_RESUME, 'fit_score' => 72.5], 'fit_score'],
            'fit score that is not a number' => [['tailored_content' => self::FIRST_RESUME, 'fit_score' => 'high'], 'fit_score'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('invalidFinalizeProvider')]
    public function test_finalize_rejects_invalid_input(array $payload, string $errorKey): void
    {
        $application = Application::factory()->withConversation()->create(['fit_score' => 40]);
        $documents = TargetedResume::query()->count();

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.finalize', $application), $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors([$errorKey]);

        $this->assertNull($application->fresh()->targeted_resume_id);
        $this->assertSame(40, $application->fresh()->fit_score);
        $this->assertSame($documents, TargetedResume::query()->count());
    }

    // ---------------------------------------------------------------------
    // Requirement: Cover letters attach to the Application
    // ---------------------------------------------------------------------

    public function test_cover_letter_for_a_main_resume_application(): void
    {
        $this->stubCoverLetterDocuments();
        $application = Application::factory()->withConversation()->create(['company_name' => 'Letterzq Co', 'position' => 'Staff Engineer']);
        $documents = TargetedResume::query()->count();
        $this->assertNull($application->targeted_resume_id);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.finalize-cover-letter', $application), ['cover_letter_content' => self::FIRST_LETTER])
            ->assertOk();

        $letter = $application->coverLetters()->sole();

        $response->assertExactJson([
            'success' => true,
            'cover_letter_id' => $letter->id,
            'message' => 'Cover letter saved successfully.',
        ]);

        $this->assertSame($application->id, $letter->application_id);
        $this->assertSame($application->resume_version_id, $letter->resume_version_id);
        $this->assertSame('Letterzq Co', $letter->company_name);
        $this->assertSame('Staff Engineer', $letter->position);
        $this->assertSame('I build reliable Laravel applications.', $letter->message_body);
        $this->assertNull($application->fresh()->targeted_resume_id);
        $this->assertSame($documents, TargetedResume::query()->count());
        $this->assertSame(ApplicationStatus::Draft, $application->fresh()->status);

        $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.show', $application))
            ->assertInertia(fn (Assert $page) => $page
                ->where('coverLetter.id', $letter->id)
                ->where('targetedResume', null)
            );
    }

    public function test_re_finalizing_a_cover_letter(): void
    {
        $this->stubCoverLetterDocuments();
        $application = Application::factory()->withConversation()->create();
        $letters = CoverLetter::query()->count();

        $first = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.finalize-cover-letter', $application), ['cover_letter_content' => self::FIRST_LETTER])
            ->assertOk()
            ->json('cover_letter_id');

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.finalize-cover-letter', $application), ['cover_letter_content' => self::SECOND_LETTER])
            ->assertOk()
            ->assertJsonPath('cover_letter_id', $first);

        $this->assertSame($letters + 1, CoverLetter::query()->count());
        $this->assertSame(1, $application->coverLetters()->count());
        $this->assertSame('I lead teams that build reliable Laravel applications.', CoverLetter::query()->findOrFail($first)->message_body);
        $this->assertSame('Best regards,', CoverLetter::query()->findOrFail($first)->closing);
    }

    public function test_cover_letter_and_targeted_resume_finalize_independently_in_either_order(): void
    {
        $this->stubCoverLetterDocuments();
        $application = Application::factory()->withConversation()->create();

        $letterId = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.finalize-cover-letter', $application), ['cover_letter_content' => self::FIRST_LETTER])
            ->assertOk()
            ->json('cover_letter_id');

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.finalize', $application), ['tailored_content' => self::FIRST_RESUME])
            ->assertOk();

        $this->assertSame($application->id, CoverLetter::query()->findOrFail($letterId)->application_id);
        $this->assertNotNull($application->fresh()->targeted_resume_id);
        $this->assertSame(1, $application->coverLetters()->count());
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function invalidCoverLetterProvider(): array
    {
        return [
            'no content' => [[]],
            'null content' => [['cover_letter_content' => null]],
            'blank content' => [['cover_letter_content' => '   ']],
            'content that is not text' => [['cover_letter_content' => ['Dear team']]],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('invalidCoverLetterProvider')]
    public function test_finalize_cover_letter_rejects_invalid_input(array $payload): void
    {
        $this->stubCoverLetterDocuments();
        $application = Application::factory()->withConversation()->create();

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.finalize-cover-letter', $application), $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['cover_letter_content']);

        $this->assertSame(0, $application->coverLetters()->count());
    }

    public function test_finalize_cover_letter_reports_a_rendering_failure(): void
    {
        $documents = $this->createStub(CoverLetterDocumentService::class);
        $documents->method('generateDocx')->willReturn(['success' => false, 'error' => 'Renderer unavailable.']);
        $documents->method('generatePdf')->willReturn(['success' => true]);
        $this->app->instance(CoverLetterDocumentService::class, $documents);
        $application = Application::factory()->withConversation()->create();

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.finalize-cover-letter', $application), ['cover_letter_content' => self::FIRST_LETTER])
            ->assertStatus(422)
            ->assertExactJson(['success' => false, 'message' => 'Renderer unavailable.']);
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function stubCoverLetterDocuments(): void
    {
        $documents = $this->createStub(CoverLetterDocumentService::class);
        $documents->method('generateDocx')->willReturn(['success' => true]);
        $documents->method('generatePdf')->willReturn(['success' => true]);
        $this->app->instance(CoverLetterDocumentService::class, $documents);
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    private function message(AiConversation|\Jvjvjv\CodeTalker\Models\AiConversation $conversation, string $role, string $content, ?array $metadata = null): AiConversationMessage
    {
        return AiConversationMessage::create([
            'ai_conversation_id' => $conversation->id,
            'role' => $role,
            'content' => $content,
            'metadata' => $metadata,
        ]);
    }

    private function entry(Application $application, ApplicationStatus $status, string $occurredAt, ?string $notes = null): ApplicationStatusUpdate
    {
        return ApplicationStatusUpdate::factory()->create([
            'application_id' => $application->id,
            'status' => $status,
            'notes' => $notes,
            'occurred_at' => Carbon::parse($occurredAt),
        ]);
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
