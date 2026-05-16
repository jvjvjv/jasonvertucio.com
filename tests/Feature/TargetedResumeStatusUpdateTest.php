<?php

namespace Tests\Feature;

use App\Enums\AiConversationStatus;
use App\Enums\TargetedResumeApplicationStatus;
use App\Enums\TargetedResumeStatus;
use App\Models\AiConversation;
use App\Models\ResumeVersion;
use App\Models\TargetedResume;
use App\Models\TargetedResumeStatusUpdate;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TargetedResumeStatusUpdateTest extends TestCase
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

    public function test_can_log_applied_status_update(): void
    {
        $conversation = AiConversation::factory()->completed()->create();
        $targetedResume = TargetedResume::factory()->finalized()->create([
            'ai_conversation_id' => $conversation->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.targeted.status-update', $conversation), [
                'status' => 'applied',
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('status', 'applied');

        $this->assertDatabaseHas('targeted_resume_status_updates', [
            'targeted_resume_id' => $targetedResume->id,
            'status' => 'applied',
        ]);

        $targetedResume->refresh();
        $this->assertSame(TargetedResumeStatus::Applied, $targetedResume->status);
    }

    public function test_applied_status_creates_history_record_with_today_date_by_default(): void
    {
        $conversation = AiConversation::factory()->completed()->create();
        TargetedResume::factory()->finalized()->create([
            'ai_conversation_id' => $conversation->id,
        ]);

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.targeted.status-update', $conversation), [
                'status' => 'applied',
            ])
            ->assertOk();

        $update = TargetedResumeStatusUpdate::query()->latest()->first();
        $this->assertNotNull($update);
        $this->assertSame(TargetedResumeApplicationStatus::Applied, $update->status);
        $this->assertTrue(Carbon::today()->isSameDay($update->occurred_at));
    }

    public function test_interviewing_status_with_scheduled_date(): void
    {
        $conversation = AiConversation::factory()->completed()->create();
        $targetedResume = TargetedResume::factory()->applied()->create([
            'ai_conversation_id' => $conversation->id,
        ]);

        $scheduledDate = '2026-06-12';

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.targeted.status-update', $conversation), [
                'status' => 'interviewing',
                'occurred_at' => $scheduledDate,
                'notes' => 'Round 1 with hiring manager',
            ]);

        $response->assertOk();

        $update = TargetedResumeStatusUpdate::query()
            ->where('targeted_resume_id', $targetedResume->id)
            ->where('status', 'interviewing')
            ->first();

        $this->assertNotNull($update);
        $this->assertSame('Round 1 with hiring manager', $update->notes);
        $this->assertSame($scheduledDate, $update->occurred_at->toDateString());

        $targetedResume->refresh();
        $this->assertSame(TargetedResumeStatus::Interviewing, $targetedResume->status);
    }

    public function test_multiple_interview_rounds_can_be_logged(): void
    {
        $conversation = AiConversation::factory()->completed()->create();
        $targetedResume = TargetedResume::factory()->applied()->create([
            'ai_conversation_id' => $conversation->id,
        ]);

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.targeted.status-update', $conversation), [
                'status' => 'interviewing',
                'occurred_at' => '2026-06-12',
                'notes' => 'Round 1',
            ])
            ->assertOk();

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.targeted.status-update', $conversation), [
                'status' => 'interviewed',
                'occurred_at' => '2026-06-12',
            ])
            ->assertOk();

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.targeted.status-update', $conversation), [
                'status' => 'interviewing',
                'occurred_at' => '2026-06-19',
                'notes' => 'Round 2',
            ])
            ->assertOk();

        $this->assertSame(3, $targetedResume->statusUpdates()->count());
        $targetedResume->refresh();
        $this->assertSame(TargetedResumeStatus::Interviewing, $targetedResume->status);
    }

    public function test_rejected_after_applied(): void
    {
        $conversation = AiConversation::factory()->completed()->create();
        $targetedResume = TargetedResume::factory()->applied()->create([
            'ai_conversation_id' => $conversation->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.targeted.status-update', $conversation), [
                'status' => 'rejected',
                'notes' => 'Position filled internally.',
            ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'rejected');
        $response->assertJsonPath('allowed_next_statuses', []);

        $targetedResume->refresh();
        $this->assertSame(TargetedResumeStatus::Rejected, $targetedResume->status);
    }

    public function test_cannot_add_status_update_to_terminal_application(): void
    {
        $conversation = AiConversation::factory()->completed()->create();
        TargetedResume::factory()->create([
            'ai_conversation_id' => $conversation->id,
            'status' => TargetedResumeStatus::Rejected,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.targeted.status-update', $conversation), [
                'status' => 'applied',
            ]);

        $response->assertStatus(422);
    }

    public function test_cannot_add_status_update_without_finalized_resume(): void
    {
        $conversation = AiConversation::factory()->completed()->create();

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.targeted.status-update', $conversation), [
                'status' => 'applied',
            ]);

        $response->assertStatus(422);
    }

    public function test_invalid_status_value_is_rejected(): void
    {
        $conversation = AiConversation::factory()->completed()->create();
        TargetedResume::factory()->finalized()->create([
            'ai_conversation_id' => $conversation->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.targeted.status-update', $conversation), [
                'status' => 'dancing',
            ]);

        $response->assertStatus(422);
    }

    public function test_index_status_filter_returns_only_matching_statuses(): void
    {
        $conv1 = AiConversation::factory()->completed()->create(['feature' => 'targeted-resume']);
        TargetedResume::factory()->create([
            'ai_conversation_id' => $conv1->id,
            'status' => TargetedResumeStatus::Rejected,
        ]);

        $conv2 = AiConversation::factory()->completed()->create(['feature' => 'targeted-resume']);
        TargetedResume::factory()->applied()->create([
            'ai_conversation_id' => $conv2->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.resume.targeted.index', ['status' => ['rejected']]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('resume/targeted/Index', false)
            ->where('conversations', fn ($conversations) => collect($conversations)->pluck('id')->contains($conv1->id)
                && !collect($conversations)->pluck('id')->contains($conv2->id)
            )
        );
    }

    public function test_allowed_next_statuses_returned_correctly_for_applied(): void
    {
        $conversation = AiConversation::factory()->completed()->create();
        TargetedResume::factory()->applied()->create([
            'ai_conversation_id' => $conversation->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.targeted.status-update', $conversation), [
                'status' => 'interviewing',
            ]);

        $response->assertOk();
        $allowedNext = $response->json('allowed_next_statuses');
        $this->assertContains('interviewed', $allowedNext);
        $this->assertContains('rejected', $allowedNext);
        $this->assertNotContains('applied', $allowedNext);
    }

    public function test_response_includes_full_status_update_history(): void
    {
        $conversation = AiConversation::factory()->completed()->create();
        $targetedResume = TargetedResume::factory()->finalized()->create([
            'ai_conversation_id' => $conversation->id,
        ]);

        TargetedResumeStatusUpdate::create([
            'targeted_resume_id' => $targetedResume->id,
            'status' => 'applied',
            'occurred_at' => now()->subDays(5),
        ]);
        $targetedResume->update(['status' => TargetedResumeStatus::Applied]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.targeted.status-update', $conversation), [
                'status' => 'interviewing',
            ]);

        $response->assertOk();
        $statusUpdates = $response->json('status_updates');
        $this->assertCount(2, $statusUpdates);
        $this->assertSame('applied', $statusUpdates[0]['status']);
        $this->assertSame('interviewing', $statusUpdates[1]['status']);
    }
}
