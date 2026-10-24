<?php

namespace Tests\Feature;

use App\Models\AiConversation;
use App\Models\Application;
use App\Models\ResumeVersion;
use App\Models\User;
use App\Services\TargetedResumeService;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Jvjvjv\CodeTalker\Models\AiSystem;
use Jvjvjv\CodeTalker\Models\AiSystemFeatureDefault;
use Tests\TestCase;

/**
 * An application already applied to needs only a fit score, so its session
 * opens with the fit-score-only message rather than the full analysis.
 */
class ApplicationAlreadyAppliedSessionTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        AiSystemFeatureDefault::query()->delete();
        ResumeVersion::query()->update(['is_current' => false]);
        ResumeVersion::factory()->create(['is_current' => true]);

        $this->admin = User::factory()->create();
        Permission::firstOrCreate(['name' => 'edit-resume']);
        $this->admin->givePermissionTo('edit-resume');
    }

    private function firstUserMessage(Application $application): string
    {
        return AiConversation::findOrFail($application->ai_conversation_id)
            ->messages()->where('role', 'user')->orderBy('id')->firstOrFail()->content;
    }

    public function test_i_applied_with_a_system_attaches_a_fit_score_session(): void
    {
        $system = AiSystem::factory()->create();

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.store'), [
                'intent' => 'applied',
                'job_description' => 'Ship features.',
                'job_title' => 'Staff Engineer',
                'company_name' => 'Fit Score Co',
                'ai_system_id' => $system->id,
            ])
            ->assertOk();

        $application = Application::findOrFail($response->json('application_id'));
        $this->assertNotNull($application->ai_conversation_id);
        $this->assertSame($system->id, $application->conversation->ai_system_id);
        $this->assertTrue($application->conversation->context['auto_start_pending']);
        $this->assertStringStartsWith(TargetedResumeService::ALREADY_APPLIED_MESSAGE, $this->firstUserMessage($application));
        $this->assertStringContainsString('Ship features.', $this->firstUserMessage($application));
    }

    public function test_i_applied_without_a_system_attaches_no_session(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.store'), [
                'intent' => 'applied',
                'job_description' => 'Ship features.',
            ])
            ->assertOk();

        $this->assertNull(Application::findOrFail($response->json('application_id'))->ai_conversation_id);
    }

    public function test_begin_analysis_on_a_draft_uses_the_full_analysis_message(): void
    {
        $application = Application::factory()->create();

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.analysis', $application), ['ai_system_id' => AiSystem::factory()->create()->id])
            ->assertOk();

        $this->assertStringStartsWith('Please begin the analysis on the following job description', $this->firstUserMessage($application->fresh()));
    }

    public function test_begin_analysis_on_an_applied_application_asks_only_for_a_fit_score(): void
    {
        $application = Application::factory()->applied()->create();

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.analysis', $application), ['ai_system_id' => AiSystem::factory()->create()->id])
            ->assertOk();

        $this->assertStringStartsWith(TargetedResumeService::ALREADY_APPLIED_MESSAGE, $this->firstUserMessage($application->fresh()));
    }
}
