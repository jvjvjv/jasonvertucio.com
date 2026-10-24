<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\User;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Jvjvjv\CodeTalker\Enums\AiConversationStatus;
use Jvjvjv\CodeTalker\Enums\AiInteractionStatus;
use Jvjvjv\CodeTalker\Models\AiConversation;
use Jvjvjv\CodeTalker\Models\AiInteractionLog;
use Jvjvjv\CodeTalker\Models\AiSystem;
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

    public function test_application_status_casts_to_enum(): void
    {
        $application = Application::factory()->create();

        $this->assertInstanceOf(ApplicationStatus::class, $application->status);
        $this->assertEquals(ApplicationStatus::Draft, $application->status);
    }

    public function test_application_status_persists_all_cases(): void
    {
        foreach (ApplicationStatus::cases() as $status) {
            $application = Application::factory()->create(['status' => $status]);

            $this->assertEquals($status, $application->fresh()->status);
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

    public function test_pass_action_updates_application_and_conversation_status(): void
    {
        Permission::firstOrCreate(['name' => 'edit-resume']);
        $user = User::factory()->create();
        $user->givePermissionTo('edit-resume');
        $this->actingAs($user);

        $conversation = AiConversation::factory()->create([
            'user_id' => $user->id,
            'status' => AiConversationStatus::Active,
        ]);
        $application = Application::factory()->create(['ai_conversation_id' => $conversation->id]);

        $response = $this->postJson(route('admin.resume.applications.pass', $application));

        $response->assertOk();
        $response->assertExactJson([
            'success' => true,
            'status' => 'passed',
            'redirect' => route('admin.resume.applications.index'),
        ]);
        $this->assertEquals(ApplicationStatus::Passed, $application->fresh()->status);
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

        $draft = Application::factory()->create();
        $passed = Application::factory()->passed()->create();
        $applied = Application::factory()->applied()->create();

        $this->assertEquals(ApplicationStatus::Draft, $draft->status);
        $this->assertEquals(ApplicationStatus::Passed, $passed->status);
        $this->assertEquals(ApplicationStatus::Applied, $applied->status);
        $this->assertSame(1, $applied->statusUpdates()->where('status', ApplicationStatus::Applied->value)->count());
    }
}
