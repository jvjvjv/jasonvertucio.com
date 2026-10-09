<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use Jvjvjv\CodeTalker\Models\AiConversation;
use App\Models\Application;
use App\Models\ResumeVersion;
use App\Models\User;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Jvjvjv\CodeTalker\Enums\AiConversationStatus;
use Jvjvjv\CodeTalker\Models\AiConversationMessage;
use Jvjvjv\CodeTalker\Models\AiSystem;
use Tests\TestCase;

class ApplicationListTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        Permission::firstOrCreate(['name' => 'edit-resume']);
        $this->admin->givePermissionTo('edit-resume');
    }

    /**
     * The ids the list page was given, in the order it was given them,
     * limited to the applications a test created (the database may hold
     * others).
     *
     * @param  array<int, Application>  $created
     * @return array<int, int>
     */
    private function listedIds(mixed $applications, array $created): array
    {
        $createdIds = collect($created)->pluck('id');

        return collect($applications)->pluck('id')->filter(fn ($id) => $createdIds->contains($id))->values()->all();
    }

    /**
     * @param  array<int, Application>  $applications
     * @return array<int, int>
     */
    private function sortedIds(array $applications): array
    {
        return collect($applications)->pluck('id')->sort()->values()->all();
    }

    private function sessionFor(Application $application, AiConversationStatus $status = AiConversationStatus::Active): AiConversation
    {
        $conversation = AiConversation::factory()->create(['feature' => 'targeted-resume', 'status' => $status]);
        $application->update(['ai_conversation_id' => $conversation->id]);

        return $conversation;
    }

    public function test_store_seeds_a_single_initial_user_message_with_analysis_prompt_and_job_description(): void
    {
        $system = AiSystem::factory()->create(['is_active' => true]);
        ResumeVersion::factory()->create(['is_current' => true]);

        $jobTitle = 'Senior Laravel Engineer';
        $jobDescription = 'Build and maintain Laravel applications.';

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.store'), [
                'intent' => 'analyze',
                'ai_system_id' => $system->id,
                'job_title' => $jobTitle,
                'job_description' => $jobDescription,
            ]);

        $response->assertOk();

        $application = Application::query()->findOrFail($response->json('application_id'));
        $response->assertJsonPath('redirect', route('admin.resume.applications.show', $application));

        $this->assertSame(ApplicationStatus::Draft, $application->status);
        $this->assertSame($jobTitle, $application->position);
        $this->assertNotNull($application->conversation);

        $messages = $application->conversation->messages()->orderBy('id')->get();

        $this->assertCount(2, $messages);
        $this->assertSame('system', $messages[0]->role);
        $this->assertSame('user', $messages[1]->role);
        $this->assertSame(
            "Please begin the analysis on the following job description\n\nJob Title: {$jobTitle}\n\nJob Description:\n\n{$jobDescription}",
            $messages[1]->content,
        );
    }

    public function test_index_defaults_to_no_status_filter(): void
    {
        $draft = Application::factory()->create(['company_name' => 'DraftCo']);
        $passed = Application::factory()->passed()->create(['company_name' => 'PassCo']);
        $applied = Application::factory()->applied()->create(['company_name' => 'AppliedCo']);
        $withResume = Application::factory()->withTargetedResume()->create(['company_name' => 'TailoredCo']);
        $created = [$draft, $passed, $applied, $withResume];

        $response = $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('resume/applications/Index', false)
            ->where('filters.statuses', [])
            ->where('filters.uses_targeted_resume', 'any')
            ->where('filters.search', '')
            ->has('allStatuses', count(ApplicationStatus::cases()))
            ->where('applications', fn ($applications) => collect($this->listedIds($applications, $created))->sort()->values()->all() === $this->sortedIds($created))
        );
    }

    public function test_index_returns_all_when_all_statuses_selected(): void
    {
        $draft = Application::factory()->create();
        $passed = Application::factory()->passed()->create();
        $applied = Application::factory()->applied()->create();
        $created = [$draft, $passed, $applied];

        $response = $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.index', [
                'status' => array_column(ApplicationStatus::cases(), 'value'),
            ]));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('resume/applications/Index', false)
            ->where('applications', fn ($applications) => collect($this->listedIds($applications, $created))->sort()->values()->all() === $this->sortedIds($created))
        );
    }

    public function test_having_a_targeted_resume_is_a_filter_not_a_status(): void
    {
        $withoutResume = Application::factory()->create(['company_name' => 'MainResumeCo']);
        $withResume = Application::factory()->withTargetedResume()->create(['company_name' => 'TailoredCo']);
        $created = [$withoutResume, $withResume];

        $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.index', ['uses_targeted_resume' => 'yes']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.uses_targeted_resume', 'yes')
                ->where('applications', fn ($applications) => $this->listedIds($applications, $created) === [$withResume->id]
                    && collect($applications)->firstWhere('id', $withResume->id)['targeted_resume_id'] === $withResume->targeted_resume_id)
            );

        $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.index', ['uses_targeted_resume' => 'no']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.uses_targeted_resume', 'no')
                ->where('applications', fn ($applications) => $this->listedIds($applications, $created) === [$withoutResume->id]
                    && collect($applications)->firstWhere('id', $withoutResume->id)['targeted_resume_id'] === null)
            );

        $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.index', ['status' => ['finalized'], 'uses_targeted_resume' => 'sometimes']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.statuses', [])
                ->where('filters.uses_targeted_resume', 'any')
                ->where('applications', fn ($applications) => count($this->listedIds($applications, $created)) === 2)
            );
    }

    public function test_filter_by_applied_status_and_exposes_latest_status_update(): void
    {
        Carbon::setTestNow('2026-05-03 09:30:00');

        $draft = Application::factory()->withTargetedResume()->create(['company_name' => 'FinalizedCo']);
        $applied = Application::factory()->applied()->create(['company_name' => 'AppliedCo']);
        $created = [$draft, $applied];

        $response = $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.index', ['status' => ['applied']]));

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('resume/applications/Index', false)
                ->where('filters.statuses', ['applied'])
                ->where('applications', function ($applications) use ($created, $applied) {
                    $row = collect($applications)->firstWhere('id', $applied->id);

                    return $this->listedIds($applications, $created) === [$applied->id]
                        && $row['company_name'] === 'AppliedCo'
                        && $row['status'] === 'applied'
                        && data_get($row, 'latest_status_update.status') === 'applied'
                        && data_get($row, 'latest_status_update.occurred_at') === '2026-05-03';
                })
        );

        Carbon::setTestNow();
    }

    public function test_updating_details_updates_title_company_and_job_title(): void
    {
        $application = Application::factory()->withTargetedResume()->create([
            'company_name' => 'Old Company',
            'position' => 'Old Title',
            'location' => 'Old Town',
            'fit_score' => 40,
            'fit_summary' => 'Old summary',
        ]);
        $conversation = $this->sessionFor($application, AiConversationStatus::Completed);
        $conversation->update(['title' => 'Original Title']);

        $response = $this->actingAs($this->admin)
            ->putJson(route('admin.resume.applications.update', $application), [
                'title' => 'Updated Title',
                'company_name' => 'New Company',
                'job_title' => 'New Title',
                'location' => null,
                'fit_score' => 88,
                'fit_summary' => null,
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $application->refresh();

        $this->assertSame('Updated Title', $conversation->fresh()->title);
        $this->assertSame('New Company', $application->company_name);
        $this->assertSame('New Title', $application->position);
        $this->assertNull($application->location);
        $this->assertSame(88, $application->fit_score);
        $this->assertNull($application->fit_summary);
        $this->assertSame(ApplicationStatus::Draft, $application->status);
        $this->assertNotNull($application->targeted_resume_id);
    }

    public function test_updating_details_rejects_an_out_of_range_fit_score(): void
    {
        $application = Application::factory()->create(['fit_score' => 40]);

        $this->actingAs($this->admin)
            ->putJson(route('admin.resume.applications.update', $application), ['fit_score' => 101])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fit_score']);

        $this->assertSame(40, $application->fresh()->fit_score);
    }

    public function test_filter_by_single_status(): void
    {
        $draft = Application::factory()->create();
        $passed = Application::factory()->passed()->create();
        $created = [$draft, $passed];

        $response = $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.index', ['status' => 'passed']));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('resume/applications/Index', false)
            ->where('filters.statuses', ['passed'])
            ->where('applications', fn ($applications) => $this->listedIds($applications, $created) === [$passed->id])
        );
    }

    public function test_filter_by_draft_excludes_an_interviewing_application_with_an_active_session(): void
    {
        $draft = Application::factory()->create();
        $this->sessionFor($draft);

        $interviewing = Application::factory()->create(['status' => ApplicationStatus::Interviewing]);
        $this->sessionFor($interviewing);

        $created = [$draft, $interviewing];

        $response = $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.index', ['status' => ['draft']]));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('resume/applications/Index', false)
            ->where('applications', fn ($applications) => $this->listedIds($applications, $created) === [$draft->id])
        );
    }

    public function test_filter_by_multiple_statuses(): void
    {
        $draft = Application::factory()->create();
        $applied = Application::factory()->applied()->create();
        $passed = Application::factory()->passed()->create();
        $created = [$draft, $applied, $passed];

        $response = $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.index', ['status' => ['draft', 'applied']]));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('resume/applications/Index', false)
            ->where('applications', fn ($applications) => collect($this->listedIds($applications, $created))->sort()->values()->all() === $this->sortedIds([$draft, $applied]))
        );
    }

    public function test_search_by_company_name(): void
    {
        $match = Application::factory()->create(['company_name' => 'Acme Zyxwv Corp', 'position' => 'Engineer']);
        Application::factory()->create(['company_name' => 'Other Inc', 'position' => 'Engineer']);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.index', ['search' => 'Zyxwv']));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('resume/applications/Index', false)
            ->where('filters.search', 'Zyxwv')
            ->where('applications', fn ($applications) => collect($applications)->pluck('id')->all() === [$match->id]
                && collect($applications)->first()['company_name'] === 'Acme Zyxwv Corp')
        );
    }

    public function test_search_by_job_title(): void
    {
        $match = Application::factory()->create(['company_name' => 'Acme', 'position' => 'Senior Qwertzu Developer']);
        Application::factory()->create(['company_name' => 'Acme', 'position' => 'Junior Designer']);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.index', ['search' => 'Qwertzu']));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('resume/applications/Index', false)
            ->where('applications', fn ($applications) => collect($applications)->pluck('id')->all() === [$match->id]
                && collect($applications)->first()['position'] === 'Senior Qwertzu Developer')
        );
    }

    public function test_search_by_message_content(): void
    {
        $match = Application::factory()->create();
        AiConversationMessage::create([
            'ai_conversation_id' => $this->sessionFor($match)->id,
            'role' => 'user',
            'content' => 'I want to apply for the blockchainzq position',
        ]);

        $other = Application::factory()->create();
        AiConversationMessage::create([
            'ai_conversation_id' => $this->sessionFor($other)->id,
            'role' => 'user',
            'content' => 'I want to apply for the marketing role',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.index', ['search' => 'blockchainzq']));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('resume/applications/Index', false)
            ->where('applications', function ($applications) use ($match) {
                $row = collect($applications)->first();

                return collect($applications)->pluck('id')->all() === [$match->id]
                    && $row['has_conversation'] === true
                    && $row['messages_count'] === 1
                    && is_array($row['usage']);
            })
        );
    }

    public function test_search_excludes_system_messages(): void
    {
        $application = Application::factory()->create();
        AiConversationMessage::create([
            'ai_conversation_id' => $this->sessionFor($application)->id,
            'role' => 'system',
            'content' => 'secret system prompt keywordzq',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.index', ['search' => 'secret system prompt keywordzq']));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('resume/applications/Index', false)
            ->where('applications', [])
        );
    }

    public function test_search_with_no_results(): void
    {
        Application::factory()->create();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.index', ['search' => 'nonexistentxyz']));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('resume/applications/Index', false)
            ->where('applications', [])
        );
    }

    public function test_combined_status_and_search_filters(): void
    {
        $draftMatch = Application::factory()->create(['company_name' => 'TargetCozq']);
        Application::factory()->passed()->create(['company_name' => 'TargetCozq Pass']);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.index', [
                'status' => ['draft'],
                'search' => 'TargetCozq',
            ]));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('resume/applications/Index', false)
            ->where('applications', fn ($applications) => collect($applications)->pluck('id')->all() === [$draftMatch->id])
        );
    }

    public function test_an_application_without_a_session_lists_with_empty_ai_usage(): void
    {
        $resumeVersion = ResumeVersion::factory()->create(['version' => '2026.9.9']);
        $application = Application::factory()->applied()->create([
            'company_name' => 'NoSessionCozq',
            'resume_version_id' => $resumeVersion->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.index', ['search' => 'NoSessionCozq']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('applications.0.id', $application->id)
                ->where('applications.0.has_conversation', false)
                ->where('applications.0.messages_count', null)
                ->where('applications.0.usage', null)
                ->where('applications.0.targeted_resume_id', null)
                ->where('applications.0.resume_version', '2026.9.9')
                ->where('applications.0.status', 'applied')
            );
    }

    public function test_most_recently_active_application_is_listed_first(): void
    {
        $stale = Application::factory()->create(['company_name' => 'Orderzq Stale']);
        $quiet = Application::factory()->create(['company_name' => 'Orderzq Quiet']);
        $chatty = Application::factory()->create(['company_name' => 'Orderzq Chatty']);

        Application::query()->whereKey($stale->id)->update(['updated_at' => now()->subDays(3)]);
        Application::query()->whereKey($quiet->id)->update(['updated_at' => now()->subDay()]);
        Application::query()->whereKey($chatty->id)->update(['updated_at' => now()->subDays(5)]);

        $message = AiConversationMessage::create([
            'ai_conversation_id' => $this->sessionFor($chatty)->id,
            'role' => 'assistant',
            'content' => 'Fresh reply',
        ]);
        Application::query()->whereKey($chatty->id)->update(['updated_at' => now()->subDays(5)]);
        AiConversationMessage::query()->whereKey($message->id)->update(['created_at' => now()->subHour()]);

        $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.index', ['search' => 'Orderzq']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('applications', fn ($applications) => collect($applications)->pluck('id')->all() === [$chatty->id, $quiet->id, $stale->id])
            );
    }

    public function test_a_deleted_application_is_not_listed(): void
    {
        $application = Application::factory()->create(['company_name' => 'DeletedCozq']);

        $this->actingAs($this->admin)
            ->delete(route('admin.resume.applications.destroy', $application))
            ->assertRedirect(route('admin.resume.applications.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted($application);

        $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.index', ['search' => 'DeletedCozq']))
            ->assertInertia(fn (Assert $page) => $page->where('applications', []));

        $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.show', $application))
            ->assertNotFound();
    }

    public function test_chat_reactivates_a_passed_session_without_changing_the_application(): void
    {
        $application = Application::factory()->passed()->create();
        $conversation = $this->sessionFor($application, AiConversationStatus::Pass);

        $this->assertEquals(AiConversationStatus::Pass, $conversation->status);

        // We can't fully test the SSE stream, but the status change happens
        // before streaming starts.
        $this->actingAs($this->admin)
            ->post(route('admin.resume.applications.chat', $application), [
                'message' => 'Continuing the conversation',
            ]);

        $this->assertEquals(AiConversationStatus::Active, $conversation->fresh()->status);
        $this->assertSame(ApplicationStatus::Passed, $application->fresh()->status);
    }

    public function test_chat_does_not_change_active_status(): void
    {
        $application = Application::factory()->create();
        $conversation = $this->sessionFor($application);

        $this->actingAs($this->admin)
            ->post(route('admin.resume.applications.chat', $application), [
                'message' => 'Hello',
            ]);

        $this->assertEquals(AiConversationStatus::Active, $conversation->fresh()->status);
    }

    public function test_chat_and_finalize_are_refused_for_an_application_without_a_session(): void
    {
        $application = Application::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.resume.applications.chat', $application), ['message' => 'Hello'])
            ->assertStatus(409)
            ->assertJsonStructure(['message']);

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.finalize', $application), ['tailored_content' => '# Summary'])
            ->assertStatus(409);

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.finalize-cover-letter', $application), ['cover_letter_content' => 'Dear team'])
            ->assertStatus(409);

        $this->assertNull($application->fresh()->targeted_resume_id);
    }

    public function test_former_builder_urls_redirect_to_their_application_pages(): void
    {
        $application = Application::factory()->create();
        $conversation = $this->sessionFor($application);
        $orphan = AiConversation::factory()->create();

        $this->actingAs($this->admin)
            ->get('/admin/resume/targeted-builder')
            ->assertStatus(301)
            ->assertRedirect(route('admin.resume.applications.index'));

        $this->actingAs($this->admin)
            ->get('/admin/resume/targeted-builder/new')
            ->assertStatus(301)
            ->assertRedirect(route('admin.resume.applications.create'));

        $this->actingAs($this->admin)
            ->get("/admin/resume/targeted-builder/{$conversation->id}")
            ->assertStatus(301)
            ->assertRedirect(route('admin.resume.applications.show', $application));

        $this->actingAs($this->admin)
            ->get("/admin/resume/targeted-builder/{$orphan->id}")
            ->assertNotFound();
    }
}
