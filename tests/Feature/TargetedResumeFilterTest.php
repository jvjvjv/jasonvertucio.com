<?php

namespace Tests\Feature;

use App\Enums\AiConversationStatus;
use App\Enums\TargetedResumeStatus;
use App\Models\AiConversation;
use App\Models\AiConversationMessage;
use App\Models\TargetedResume;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
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
        $active = AiConversation::factory()->active()->create();
        $completed = AiConversation::factory()->completed()->create();
        $pass = AiConversation::factory()->pass()->create();

        $finalized = AiConversation::factory()->completed()->create();
        TargetedResume::factory()->create([
            'ai_conversation_id' => $finalized->id,
            'status' => TargetedResumeStatus::Finalized,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.resume.targeted.index'));

        $response->assertStatus(200);
        $response->assertSee($active->title);
        $response->assertSee($finalized->title);
        $response->assertDontSee($pass->title);
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
        $response->assertSee($active->title);
        $response->assertSee($completed->title);
        $response->assertSee($pass->title);
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
        $response->assertSee('FinalizedCo');
        $response->assertDontSee('DraftCo');
    }

    public function test_filter_by_single_status(): void
    {
        $active = AiConversation::factory()->active()->create();
        $pass = AiConversation::factory()->pass()->create();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.resume.targeted.index', ['status' => ['active']]));

        $response->assertStatus(200);
        $response->assertSee($active->title);
        $response->assertDontSee($pass->title);
    }

    public function test_filter_by_multiple_statuses(): void
    {
        $active = AiConversation::factory()->active()->create();
        $completed = AiConversation::factory()->completed()->create();
        $pass = AiConversation::factory()->pass()->create();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.resume.targeted.index', ['status' => ['active', 'completed']]));

        $response->assertStatus(200);
        $response->assertSee($active->title);
        $response->assertSee($completed->title);
        $response->assertDontSee($pass->title);
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
        $response->assertSee('Acme Corp');
        $response->assertDontSee('Other Inc');
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
        $response->assertSee('Senior Laravel Developer');
        $response->assertDontSee('Junior Designer');
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
        $response->assertSee($conversation->title);
        $response->assertDontSee($other->title);
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
        $response->assertDontSee($conversation->title);
    }

    public function test_search_with_no_results(): void
    {
        AiConversation::factory()->create();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.resume.targeted.index', ['search' => 'nonexistentxyz']));

        $response->assertStatus(200);
        $response->assertSee('No targeted resume chats yet');
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
        $response->assertSee('TargetCo');
        $response->assertDontSee('TargetCo Pass');
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
