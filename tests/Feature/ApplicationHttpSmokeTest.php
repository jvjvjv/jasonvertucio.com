<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\CoverLetter;
use App\Models\ResumeVersion;
use App\Models\TargetedResume;
use App\Models\User;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Jvjvjv\CodeTalker\Models\AiConversationMessage;
use Jvjvjv\CodeTalker\Models\AiSystem;
use Tests\TestCase;

/**
 * Wiring checks for the application HTTP layer: each page hands its
 * component the agreed props and each endpoint answers in the agreed shape.
 * The spec scenarios have their own suites.
 */
class ApplicationHttpSmokeTest extends TestCase
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

    public function test_create_page_offers_systems_and_resume_versions(): void
    {
        $current = ResumeVersion::factory()->create(['is_current' => true]);

        $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('resume/applications/Create', false)
                ->has('systems')
                ->has('defaultSystemId')
                ->has('coverLetterDefaultId')
                ->where('resumeVersions.0', ['id' => $current->id, 'version' => $current->version, 'is_current' => true])
                ->has('currentResumeVersionId')
            );
    }

    public function test_store_applied_records_an_application_with_no_session(): void
    {
        $version = ResumeVersion::factory()->create();

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.store'), [
                'intent' => 'applied',
                'job_description' => 'Role details',
                'company_name' => 'Applied Co',
                'job_title' => 'Engineer',
                'job_location' => 'Remote',
                'resume_version_id' => $version->id,
                'occurred_at' => '2026-07-01',
            ]);

        $response->assertOk();

        $application = Application::query()->findOrFail($response->json('application_id'));

        $response->assertExactJson([
            'application_id' => $application->id,
            'redirect' => route('admin.resume.applications.show', $application),
        ]);
        $this->assertSame(ApplicationStatus::Applied, $application->status);
        $this->assertNull($application->ai_conversation_id);
        $this->assertSame($version->id, $application->resume_version_id);
        $this->assertSame('Remote', $application->location);
        $this->assertSame('2026-07-01', $application->statusUpdates()->first()->occurred_at->toDateString());
    }

    public function test_store_validates_its_input(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.store'), ['intent' => 'analyze'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['job_description', 'ai_system_id']);

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.store'), ['intent' => 'maybe', 'job_description' => 'x'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['intent']);

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.store'), ['intent' => 'applied', 'job_description' => 'x', 'resume_version_id' => 999999999])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['resume_version_id']);
    }

    public function test_show_page_for_an_application_without_a_session(): void
    {
        $application = Application::factory()->applied()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.show', $application))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('resume/applications/Show', false)
                ->where('application.id', $application->id)
                ->where('application.status', 'applied')
                ->where('application.has_applied', true)
                ->where('application.resume_version.id', $application->resume_version_id)
                ->has('application.status_updates', 1)
                ->where('application.allowed_next_statuses', ['interviewing', 'offered', 'rejected'])
                ->where('application.job_url', null)
                ->where('conversation', null)
                ->where('messages', [])
                ->where('targetedResume', null)
                ->where('coverLetter', null)
                ->where('shouldAutoStart', false)
                ->has('resumeVersions')
                ->has('currentResumeVersionId')
                ->has('systems')
                ->has('defaultSystemId')
                ->has('ghostedAfterDays')
            );
    }

    public function test_show_page_for_an_application_with_a_session_and_documents(): void
    {
        $application = Application::factory()->withTargetedResume()->withConversation()->create();
        $application->conversation->update(['context' => ['auto_start_pending' => true]]);
        AiConversationMessage::create(['ai_conversation_id' => $application->ai_conversation_id, 'role' => 'system', 'content' => 'hidden']);
        AiConversationMessage::create(['ai_conversation_id' => $application->ai_conversation_id, 'role' => 'user', 'content' => 'visible']);
        $coverLetter = CoverLetter::create([
            'application_id' => $application->id,
            'resume_version_id' => $application->resume_version_id,
            'company_name' => $application->company_name,
            'position' => $application->position,
            'date' => '2026-07-01',
            'greeting' => 'Dear Hiring Team,',
            'message_body' => 'Body.',
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.show', $application))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('application.has_applied', false)
                ->where('application.allowed_next_statuses', ['applied'])
                ->where('conversation.id', $application->ai_conversation_id)
                ->has('conversation.usage.total_tokens')
                ->has('messages', 1)
                ->where('messages.0.content', 'visible')
                ->where('targetedResume.id', $application->targeted_resume_id)
                ->has('targetedResume.tailored_content')
                ->where('targetedResume.docx_path', false)
                ->where('coverLetter.id', $coverLetter->id)
                ->where('shouldAutoStart', true)
            );
    }

    public function test_analysis_attaches_a_session_once(): void
    {
        $system = AiSystem::factory()->create(['is_active' => true]);
        $application = Application::factory()->applied()->create();

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.analysis', $application), ['ai_system_id' => $system->id]);

        $response->assertOk();

        $application->refresh();

        $response->assertExactJson([
            'success' => true,
            'conversation_id' => $application->ai_conversation_id,
            'redirect' => route('admin.resume.applications.show', $application),
        ]);
        $this->assertNotNull($application->ai_conversation_id);
        $this->assertSame(ApplicationStatus::Applied, $application->status);
        $this->assertSame(1, $application->statusUpdates()->count());

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.analysis', $application), ['ai_system_id' => $system->id])
            ->assertStatus(409)
            ->assertJsonStructure(['message']);
    }

    public function test_targeted_resumes_list_and_editor(): void
    {
        $draft = Application::factory()->withTargetedResume()->create(['company_name' => 'Listzq Draft Co']);
        $applied = Application::factory()->withTargetedResume()->applied()->create(['company_name' => 'Listzq Applied Co']);
        Application::factory()->create(['company_name' => 'Listzq Main Resume Co']);
        $deleted = Application::factory()->withTargetedResume()->create(['company_name' => 'Listzq Deleted Co']);
        $deleted->delete();

        TargetedResume::query()->whereKey($draft->targeted_resume_id)->update(['updated_at' => now()->subDay()]);

        $this->actingAs($this->admin)
            ->get(route('admin.resume.targeted.index', ['search' => 'Listzq']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('resume/targeted/Index', false)
                ->where('filters.search', 'Listzq')
                ->has('targetedResumes', 2)
                ->where('targetedResumes.0.id', $applied->targeted_resume_id)
                ->where('targetedResumes.0.application_id', $applied->id)
                ->where('targetedResumes.0.can_discard', false)
                ->where('targetedResumes.1.id', $draft->targeted_resume_id)
                ->where('targetedResumes.1.company_name', 'Listzq Draft Co')
                ->where('targetedResumes.1.can_discard', true)
                ->has('targetedResumes.1.updated_at_human')
            );

        $this->actingAs($this->admin)
            ->get(route('admin.resume.targeted.edit', $draft->targeted_resume_id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('resume/targeted/Edit', false)
                ->where('targetedResume.id', $draft->targeted_resume_id)
                ->where('targetedResume.can_discard', true)
                ->has('targetedResume.tailored_content')
                ->where('application', ['id' => $draft->id, 'company_name' => 'Listzq Draft Co', 'position' => $draft->position])
            );

        $this->actingAs($this->admin)
            ->get(route('admin.resume.targeted.edit', $deleted->targeted_resume_id))
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->get('/admin/resume/targeted-resumes/999999999/edit')
            ->assertNotFound();
    }

    public function test_discarding_a_targeted_resume(): void
    {
        $draft = Application::factory()->withTargetedResume()->create();
        $targetedResumeId = $draft->targeted_resume_id;

        $this->actingAs($this->admin)
            ->delete(route('admin.resume.targeted.destroy', $targetedResumeId))
            ->assertRedirect(route('admin.resume.applications.show', $draft))
            ->assertSessionHas('success');

        $this->assertNull($draft->fresh()->targeted_resume_id);
        $this->assertDatabaseMissing('targeted_resumes', ['id' => $targetedResumeId]);
    }

    public function test_discard_is_refused_once_applied_however_it_is_asked(): void
    {
        $applied = Application::factory()->withTargetedResume()->applied()->create();
        $url = route('admin.resume.targeted.destroy', $applied->targeted_resume_id);

        $this->actingAs($this->admin)->deleteJson($url)->assertStatus(409)->assertJsonStructure(['message']);
        $this->actingAs($this->admin)->delete($url)->assertStatus(409);

        $this->actingAs($this->admin)
            ->from(route('admin.resume.targeted.index'))
            ->delete($url, [], ['X-Inertia' => 'true'])
            ->assertRedirect(route('admin.resume.targeted.index'))
            ->assertSessionHas('error');

        $this->assertNotNull($applied->fresh()->targeted_resume_id);
    }

    public function test_pages_and_endpoints_require_the_edit_resume_permission(): void
    {
        $application = Application::factory()->create();
        $stranger = User::factory()->create();

        $this->get(route('admin.resume.applications.index'))->assertRedirect(route('login'));

        $this->actingAs($stranger)->get(route('admin.resume.applications.index'))->assertForbidden();
        $this->actingAs($stranger)->get(route('admin.resume.targeted.index'))->assertForbidden();
        $this->actingAs($stranger)->postJson(route('admin.resume.applications.pass', $application))->assertForbidden();
    }
}
