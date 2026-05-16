<?php

namespace Tests\Feature;

use App\Enums\AiConversationStatus;
use App\Enums\TargetedResumeStatus;
use App\Models\AiSystem;
use App\Models\AiConversation;
use App\Models\AiConversationMessage;
use App\Models\ResumeVersion;
use App\Models\TargetedResume;
use App\Models\TargetedResumeStatusUpdate;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TargetedResumeFilterTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        Permission::findOrCreate('edit-resume', 'web');
        $this->admin->givePermissionTo('edit-resume');
    }

    public function test_start_seeds_a_single_initial_user_message_with_analysis_prompt_and_job_description(): void
    {
        $system = AiSystem::factory()->create(['is_active' => true]);
        ResumeVersion::factory()->create(['is_current' => true]);

        $jobTitle = 'Senior Laravel Engineer';
        $jobDescription = 'Build and maintain Laravel applications.';

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.targeted.start'), [
                'ai_system_id' => $system->id,
                'job_title' => $jobTitle,
                'job_description' => $jobDescription,
            ]);

        $response->assertOk();

        $conversation = AiConversation::query()->findOrFail($response->json('conversation_id'));
        $messages = $conversation->messages()->orderBy('id')->get();

        $this->assertCount(2, $messages);
        $this->assertSame('system', $messages[0]->role);
        $this->assertSame('user', $messages[1]->role);
        $this->assertSame(
            "Please begin the analysis on the following job description\n\nJob Title: {$jobTitle}\n\nJob Description:\n\n{$jobDescription}",
            $messages[1]->content,
        );
    }

    public function test_index_defaults_to_active_and_finalized(): void
    {
        $active = AiConversation::factory()->active()->create([
            'context' => ['company_name' => 'ActiveCo'],
        ]);
        $completed = AiConversation::factory()->completed()->create([
            'context' => ['company_name' => 'CompletedCo'],
        ]);
        $pass = AiConversation::factory()->pass()->create([
            'context' => ['company_name' => 'PassCo'],
        ]);

        $finalized = AiConversation::factory()->completed()->create([
            'context' => ['company_name' => 'FinalizedCo'],
        ]);
        TargetedResume::factory()->create([
            'ai_conversation_id' => $finalized->id,
            'status' => TargetedResumeStatus::Finalized,
            'company_name' => 'FinalizedCo',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.resume.targeted.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('resume/targeted/Index', false)
            ->where('filters.statuses', ['active', 'finalized'])
            ->where('conversations', fn ($conversations) => collect($conversations)->pluck('id')->sort()->values()->all() === collect([$active->id, $finalized->id])->sort()->values()->all())
        );
    }

    public function test_index_returns_all_when_all_statuses_selected(): void
    {
        $active = AiConversation::factory()->active()->create();
        $completed = AiConversation::factory()->completed()->create();
        $pass = AiConversation::factory()->pass()->create();
        $applied = AiConversation::factory()->completed()->create();
        TargetedResume::factory()->applied()->create([
            'ai_conversation_id' => $applied->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.resume.targeted.index', [
                'status' => ['active', 'completed', 'pass', 'draft', 'finalized', 'applied'],
            ]));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('resume/targeted/Index', false)
                ->where('conversations', fn($conversations) => collect($conversations)->pluck('id')->sort()->values()->all() === collect([$active->id, $completed->id, $pass->id, $applied->id])->sort()->values()->all())
        );
    }

    public function test_filter_by_finalized_resume_status(): void
    {
        $draft = AiConversation::factory()->active()->create();
        TargetedResume::factory()->create([
            'ai_conversation_id' => $draft->id,
            'status' => TargetedResumeStatus::Draft,
            'company_name' => 'DraftCo',
        ]);

        $finalized = AiConversation::factory()->completed()->create();
        TargetedResume::factory()->create([
            'ai_conversation_id' => $finalized->id,
            'status' => TargetedResumeStatus::Finalized,
            'company_name' => 'FinalizedCo',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.resume.targeted.index', ['status' => ['finalized']]));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('resume/targeted/Index', false)
            ->where('conversations', fn ($conversations) => collect($conversations)->count() === 1
                && data_get(collect($conversations)->first(), 'targeted_resume.company_name') === 'FinalizedCo')
        );
    }

    public function test_filter_by_applied_resume_status_and_exposes_latest_status_update(): void {
        Carbon::setTestNow('2026-05-03 09:30:00');

        $finalized = AiConversation::factory()->completed()->create();
        TargetedResume::factory()->finalized()->create([
            'ai_conversation_id' => $finalized->id,
            'company_name' => 'FinalizedCo',
        ]);

        $applied = AiConversation::factory()->completed()->create();
        $appliedResume = TargetedResume::factory()->applied()->create([
            'ai_conversation_id' => $applied->id,
            'company_name' => 'AppliedCo',
        ]);
        \App\Models\TargetedResumeStatusUpdate::create([
            'targeted_resume_id' => $appliedResume->id,
            'status' => 'applied',
            'occurred_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.resume.targeted.index', ['status' => ['applied']]));

        $response->assertStatus(200);
        $response->assertInertia(
            fn(Assert $page) => $page
                ->component('resume/targeted/Index', false)
                ->where('conversations', fn($conversations) => collect($conversations)->count() === 1
                    && data_get(collect($conversations)->first(), 'targeted_resume.company_name') === 'AppliedCo'
                    && data_get(collect($conversations)->first(), 'targeted_resume.latest_status_update.occurred_at') === '2026-05-03')
        );

        Carbon::setTestNow();
    }

    public function test_logging_applied_status_update_sets_status_and_creates_history(): void {
        Carbon::setTestNow('2026-05-03 14:15:00');

        $conversation = AiConversation::factory()->completed()->create();
        $targetedResume = TargetedResume::factory()->finalized()->create([
            'ai_conversation_id' => $conversation->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.targeted.status-update', $conversation), [
                'status' => 'applied',
                'occurred_at' => '2026-05-03',
            ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'applied');

        $targetedResume->refresh();

        $this->assertSame(TargetedResumeStatus::Applied, $targetedResume->status);
        $this->assertDatabaseHas('targeted_resume_status_updates', [
            'targeted_resume_id' => $targetedResume->id,
            'status' => 'applied',
        ]);

        Carbon::setTestNow();
    }

    public function test_updating_metadata_updates_title_company_and_job_title(): void
    {
        $conversation = AiConversation::factory()->completed()->create([
            'title' => 'Original Title',
            'context' => [
                'company_name' => 'Old Company',
                'job_title' => 'Old Title',
            ],
        ]);

        $targetedResume = TargetedResume::factory()->finalized()->create([
            'ai_conversation_id' => $conversation->id,
            'company_name' => 'Old Company',
            'position' => 'Old Title',
        ]);

        $response = $this->actingAs($this->admin)
            ->put(route('admin.resume.targeted.update-metadata', $conversation), [
                'title' => 'Updated Title',
                'company_name' => 'New Company',
                'job_title' => 'New Title',
            ]);

        $response->assertRedirect(route('admin.resume.targeted.show', $conversation));

        $conversation->refresh();
        $targetedResume->refresh();

        $this->assertSame('Updated Title', $conversation->title);
        $this->assertSame('New Company', data_get($conversation->context, 'company_name'));
        $this->assertSame('New Title', data_get($conversation->context, 'job_title'));
        $this->assertSame('New Company', $targetedResume->company_name);
        $this->assertSame('New Title', $targetedResume->position);
        $this->assertSame(TargetedResumeStatus::Finalized, $targetedResume->status);
    }

    public function test_filter_by_single_status(): void
    {
        $active = AiConversation::factory()->active()->create();
        $pass = AiConversation::factory()->pass()->create();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.resume.targeted.index', ['status' => ['active']]));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('resume/targeted/Index', false)
            ->where('conversations', fn ($conversations) => collect($conversations)->count() === 1
                && collect($conversations)->first()['id'] === $active->id)
        );
    }

    public function test_filter_by_multiple_statuses(): void
    {
        $active = AiConversation::factory()->active()->create();
        $completed = AiConversation::factory()->completed()->create();
        $pass = AiConversation::factory()->pass()->create();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.resume.targeted.index', ['status' => ['active', 'completed']]));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('resume/targeted/Index', false)
            ->where('conversations', fn ($conversations) => collect($conversations)->pluck('id')->sort()->values()->all() === collect([$active->id, $completed->id])->sort()->values()->all())
        );
    }

    public function test_search_by_company_name(): void
    {
        $conversation = AiConversation::factory()->create();
        TargetedResume::factory()->create([
            'ai_conversation_id' => $conversation->id,
            'company_name' => 'Acme Corp',
        ]);

        $other = AiConversation::factory()->create();
        TargetedResume::factory()->create([
            'ai_conversation_id' => $other->id,
            'company_name' => 'Other Inc',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.resume.targeted.index', ['search' => 'Acme']));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('resume/targeted/Index', false)
            ->where('conversations', fn ($conversations) => collect($conversations)->count() === 1
                && data_get(collect($conversations)->first(), 'targeted_resume.company_name') === 'Acme Corp')
        );
    }

    public function test_search_by_job_title(): void
    {
        $conversation = AiConversation::factory()->create();
        TargetedResume::factory()->create([
            'ai_conversation_id' => $conversation->id,
            'position' => 'Senior Laravel Developer',
        ]);

        $other = AiConversation::factory()->create();
        TargetedResume::factory()->create([
            'ai_conversation_id' => $other->id,
            'position' => 'Junior Designer',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.resume.targeted.index', ['search' => 'Laravel']));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('resume/targeted/Index', false)
            ->where('conversations', fn ($conversations) => collect($conversations)->count() === 1
                && data_get(collect($conversations)->first(), 'targeted_resume.position') === 'Senior Laravel Developer')
        );
    }

    public function test_search_by_message_content(): void
    {
        $conversation = AiConversation::factory()->create();
        AiConversationMessage::create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'I want to apply for the blockchain position',
        ]);

        $other = AiConversation::factory()->create();
        AiConversationMessage::create([
            'ai_conversation_id' => $other->id,
            'role' => 'user',
            'content' => 'I want to apply for the marketing role',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.resume.targeted.index', ['search' => 'blockchain']));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('resume/targeted/Index', false)
            ->where('conversations', fn ($conversations) => collect($conversations)->count() === 1
                && collect($conversations)->first()['id'] === $conversation->id)
        );
    }

    public function test_search_excludes_system_messages(): void
    {
        $conversation = AiConversation::factory()->create();
        AiConversationMessage::create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'system',
            'content' => 'secret system prompt keyword',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.resume.targeted.index', ['search' => 'secret system prompt']));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('resume/targeted/Index', false)
            ->where('conversations', [])
        );
    }

    public function test_search_with_no_results(): void
    {
        AiConversation::factory()->create();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.resume.targeted.index', ['search' => 'nonexistentxyz']));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('resume/targeted/Index', false)
            ->where('conversations', [])
        );
    }

    public function test_combined_status_and_search_filters(): void
    {
        $activeMatch = AiConversation::factory()->active()->create();
        TargetedResume::factory()->create([
            'ai_conversation_id' => $activeMatch->id,
            'company_name' => 'TargetCo',
        ]);

        $passMatch = AiConversation::factory()->pass()->create();
        TargetedResume::factory()->create([
            'ai_conversation_id' => $passMatch->id,
            'company_name' => 'TargetCo Pass',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.resume.targeted.index', [
                'status' => ['active'],
                'search' => 'TargetCo',
            ]));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('resume/targeted/Index', false)
            ->where('conversations', fn ($conversations) => collect($conversations)->count() === 1
                && data_get(collect($conversations)->first(), 'targeted_resume.company_name') === 'TargetCo')
        );
    }

    public function test_chat_reactivates_pass_conversation(): void
    {
        $conversation = AiConversation::factory()->pass()->create();

        $this->assertEquals(AiConversationStatus::Pass, $conversation->status);

        // We can't fully test the SSE stream, but we can verify the status change
        // by making the request (it will fail at the service layer but the status
        // update happens before streaming)
        $this->actingAs($this->admin)
            ->post(route('admin.resume.targeted.chat', $conversation), [
                'message' => 'Continuing the conversation',
            ]);

        $this->assertEquals(AiConversationStatus::Active, $conversation->fresh()->status);
    }

    public function test_chat_does_not_change_active_status(): void
    {
        $conversation = AiConversation::factory()->active()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.resume.targeted.chat', $conversation), [
                'message' => 'Hello',
            ]);

        $this->assertEquals(AiConversationStatus::Active, $conversation->fresh()->status);
    }
}
