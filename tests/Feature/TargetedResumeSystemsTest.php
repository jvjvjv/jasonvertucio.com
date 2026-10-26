<?php

namespace Tests\Feature;

use App\Models\AiConversation;
use App\Models\Application;
use App\Models\User;
use App\Services\TargetedResumeService;
use App\Services\TargetedResumeSystems;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Jvjvjv\CodeTalker\Models\AiSystem;
use Jvjvjv\CodeTalker\Models\AiSystemFeatureDefault;
use Tests\TestCase;

class TargetedResumeSystemsTest extends TestCase
{
    use DatabaseTransactions;

    private TargetedResumeSystems $systems;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        AiSystemFeatureDefault::query()->delete();
        AiSystem::query()->whereIn('system_prompt_id', TargetedResumeSystems::PROMPT_IDS)->update(['system_prompt_id' => null]);
        $this->systems = app(TargetedResumeSystems::class);

        $this->admin = User::factory()->create();
        Permission::firstOrCreate(['name' => 'edit-resume']);
        $this->admin->givePermissionTo('edit-resume');
    }

    public function test_defaults_and_matching_prompts_are_eligible(): void
    {
        $resumeDefault = AiSystem::factory()->create(['name' => 'Trsys A resume default']);
        AiSystemFeatureDefault::create(['ai_system_id' => $resumeDefault->id, 'feature' => 'targeted-resume']);
        $coverLetterDefault = AiSystem::factory()->create(['name' => 'Trsys B cover letter default']);
        AiSystemFeatureDefault::create(['ai_system_id' => $coverLetterDefault->id, 'feature' => 'cover-letter']);
        $resumePrompt = AiSystem::factory()->create(['name' => 'Trsys C', 'system_prompt_id' => TargetedResumeService::PROMPT_ID_TARGETED_RESUME]);
        $coverLetterPrompt = AiSystem::factory()->create(['name' => 'Trsys D', 'system_prompt_id' => TargetedResumeService::PROMPT_ID_COVER_LETTER]);

        $this->assertSame(
            [$resumeDefault->id, $coverLetterDefault->id, $resumePrompt->id, $coverLetterPrompt->id],
            $this->systems->selectable()->pluck('id')->all(),
        );
    }

    public function test_other_systems_are_not_eligible(): void
    {
        $inactive = AiSystem::factory()->targetedResume()->inactive()->create();
        $voice = AiSystem::factory()->audio()->create(['system_prompt_id' => TargetedResumeService::PROMPT_ID_TARGETED_RESUME]);
        $plain = AiSystem::factory()->create(['system_prompt_id' => null]);
        $otherFeature = AiSystem::factory()->create();
        AiSystemFeatureDefault::create(['ai_system_id' => $otherFeature->id, 'feature' => 'company-intel']);

        foreach ([$inactive, $voice, $plain, $otherFeature] as $system) {
            $this->assertFalse($this->systems->isEligible($system), "System {$system->id} should not be eligible");
        }

        $this->assertTrue($this->systems->selectable()->isEmpty());
    }

    public function test_the_new_session_page_offers_only_eligible_systems(): void
    {
        $eligible = AiSystem::factory()->targetedResume()->create();
        $plain = AiSystem::factory()->create(['system_prompt_id' => null]);
        AiSystemFeatureDefault::create(['ai_system_id' => $plain->id, 'feature' => 'company-intel']);

        $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('systems', fn ($systems): bool => collect($systems)->pluck('id')->all() === [$eligible->id]));
    }

    public function test_an_ineligible_system_cannot_begin_an_analysis_from_new_session(): void
    {
        $plain = AiSystem::factory()->create(['system_prompt_id' => null]);
        $applications = Application::withTrashed()->count();

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.store'), [
                'intent' => 'analyze',
                'ai_system_id' => $plain->id,
                'job_description' => 'Build ineligible things.',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ai_system_id' => TargetedResumeSystems::INELIGIBLE_MESSAGE]);

        $this->assertSame($applications, Application::withTrashed()->count());
    }

    public function test_an_ineligible_system_cannot_attach_a_fit_score_session(): void
    {
        $plain = AiSystem::factory()->create(['system_prompt_id' => null]);
        $applications = Application::withTrashed()->count();

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.store'), [
                'intent' => 'applied',
                'ai_system_id' => $plain->id,
                'job_description' => 'Applied with an ineligible system.',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ai_system_id']);

        $this->assertSame($applications, Application::withTrashed()->count());
    }

    public function test_an_ineligible_system_cannot_begin_analysis_later(): void
    {
        $application = Application::factory()->create();
        $conversations = AiConversation::withTrashed()->count();

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.analysis', $application), [
                'ai_system_id' => AiSystem::factory()->create(['system_prompt_id' => null])->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ai_system_id' => TargetedResumeSystems::INELIGIBLE_MESSAGE]);

        $this->assertNull($application->fresh()->ai_conversation_id);
        $this->assertSame($conversations, AiConversation::withTrashed()->count());
    }
}
