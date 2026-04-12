<?php

namespace Tests\Feature;

use App\Enums\AiConversationStatus;
use App\Enums\TargetedResumeStatus;
use App\Models\AiConversation;
use App\Models\AiConversationMessage;
use App\Models\TargetedResume;
use App\Models\User;
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

        $response = $this->actingAs($this->admin)
            ->get(route('admin.resume.targeted.index', [
                'status' => ['active', 'completed', 'pass', 'draft', 'finalized'],
            ]));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('resume/targeted/Index', false)
            ->where('conversations', fn ($conversations) => collect($conversations)->pluck('id')->sort()->values()->all() === collect([$active->id, $completed->id, $pass->id])->sort()->values()->all())
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
