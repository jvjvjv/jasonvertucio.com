<?php

namespace Tests\Feature;

use Jvjvjv\CodeTalker\Enums\AiConversationStatus;
use Jvjvjv\CodeTalker\Enums\AiInteractionStatus;
use App\Enums\TargetedResumeStatus;
use Jvjvjv\CodeTalker\Models\AiConversation;
use Jvjvjv\CodeTalker\Models\AiInteractionLog;
use Jvjvjv\CodeTalker\Models\AiSystem;
use App\Models\TargetedResume;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Tests\TestCase;

class AiConversationStatusTest extends TestCase
{
    use DatabaseTransactions;

    public function test_ai_conversation_status_casts_to_enum(): void
    {
        $conversation = AiConversation::factory()->create();

        $this->assertInstanceOf(AiConversationStatus::class, $conversation->status);
        $this->assertEquals(AiConversationStatus::Active, $conversation->status);
    }

    public function test_ai_conversation_status_persists_all_cases(): void
    {
        foreach (AiConversationStatus::cases() as $status) {
            $conversation = AiConversation::factory()->create(['status' => $status]);

            $this->assertEquals($status, $conversation->fresh()->status);
        }
    }

    public function test_targeted_resume_status_casts_to_enum(): void
    {
        $resume = TargetedResume::factory()->create();

        $this->assertInstanceOf(TargetedResumeStatus::class, $resume->status);
        $this->assertEquals(TargetedResumeStatus::Draft, $resume->status);
    }

    public function test_targeted_resume_status_persists_all_cases(): void
    {
        foreach (TargetedResumeStatus::cases() as $status) {
            $resume = TargetedResume::factory()->create(['status' => $status]);

            $this->assertEquals($status, $resume->fresh()->status);
        }
    }

    public function test_ai_interaction_log_status_casts_to_enum(): void
    {
        $system = AiSystem::factory()->create();
        $user = User::factory()->create();
        $conversation = AiConversation::factory()->create([
            'user_id' => $user->id,
            'ai_system_id' => $system->id,
        ]);

        $log = AiInteractionLog::create([
            'ai_system_id' => $system->id,
            'ai_conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'feature' => 'targeted-resume',
            'model' => 'test-model',
            'duration_ms' => 100,
            'status' => AiInteractionStatus::Success,
        ]);

        $this->assertInstanceOf(AiInteractionStatus::class, $log->fresh()->status);
        $this->assertEquals(AiInteractionStatus::Success, $log->fresh()->status);
    }

    public function test_pass_action_updates_conversation_status(): void
    {
        Permission::firstOrCreate(['name' => 'edit-resume']);
        $user = User::factory()->create();
        $user->givePermissionTo('edit-resume');
        $this->actingAs($user);

        $conversation = AiConversation::factory()->create([
            'user_id' => $user->id,
            'status' => AiConversationStatus::Active,
        ]);

        $response = $this->post(route('admin.resume.targeted.pass', $conversation));

        $response->assertRedirect(route('admin.resume.targeted.index'));
        $this->assertEquals(AiConversationStatus::Pass, $conversation->fresh()->status);
    }

    public function test_factory_states_produce_correct_statuses(): void
    {
        $active = AiConversation::factory()->active()->create();
        $completed = AiConversation::factory()->completed()->create();
        $pass = AiConversation::factory()->pass()->create();

        $this->assertEquals(AiConversationStatus::Active, $active->status);
        $this->assertEquals(AiConversationStatus::Completed, $completed->status);
        $this->assertEquals(AiConversationStatus::Pass, $pass->status);

        $draft = TargetedResume::factory()->draft()->create();
        $finalized = TargetedResume::factory()->finalized()->create();

        $this->assertEquals(TargetedResumeStatus::Draft, $draft->status);
        $this->assertEquals(TargetedResumeStatus::Finalized, $finalized->status);
    }
}
