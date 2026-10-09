<?php

namespace Tests\Feature;

use App\Exceptions\TargetedResumeAlreadySentException;
use App\Models\Application;
use App\Models\CoverLetter;
use App\Models\ResumeVersion;
use App\Models\TargetedResume;
use App\Models\User;
use App\Services\ApplicationService;
use App\Services\TargetedResumeService;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Guards around application data that a wrong assumption would open: the
 * cover-letter pages sit behind a different permission than applications, a
 * deleted application's resume is still the record of what was sent, and a
 * database failure must not reach the browser verbatim.
 */
class ApplicationAccessHardeningTest extends TestCase
{
    use DatabaseTransactions;

    private User $resumeEditor;

    private User $coverLetterOnly;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'edit-resume']);
        Permission::firstOrCreate(['name' => 'manage-unauthenticated-viewers']);

        $this->resumeEditor = User::factory()->create();
        $this->resumeEditor->givePermissionTo('edit-resume');

        $this->coverLetterOnly = User::factory()->create();
        $this->coverLetterOnly->givePermissionTo('manage-unauthenticated-viewers');
    }

    public function test_cover_letter_form_does_not_reveal_an_application_without_resume_editing_permission(): void
    {
        $application = Application::factory()->create(['company_name' => 'Private Employer']);

        $this->actingAs($this->coverLetterOnly)
            ->get(route('admin.cover-letters.create', ['application' => $application->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('cover-letters/Create', false)->where('application', null));
    }

    public function test_cover_letter_cannot_be_linked_to_an_application_without_resume_editing_permission(): void
    {
        $application = Application::factory()->create();

        $this->actingAs($this->coverLetterOnly)
            ->post(route('admin.cover-letters.store'), $this->coverLetterPayload(['application_id' => $application->id]))
            ->assertSessionHasErrors(['application_id']);

        $this->assertSame(0, CoverLetter::query()->where('company_name', 'Hardening Check Co')->count());
        $this->assertSame(0, $application->coverLetters()->count());
    }

    public function test_an_applications_cover_letter_cannot_be_detached_without_resume_editing_permission(): void
    {
        $application = Application::factory()->create();
        $payload = $this->coverLetterPayload();
        $coverLetter = CoverLetter::create($payload + ['application_id' => $application->id]);

        foreach ([null, '', $application->id] as $sent) {
            $this->actingAs($this->coverLetterOnly)
                ->put(route('admin.cover-letters.update', $coverLetter), $payload + ['application_id' => $sent])
                ->assertSessionHasErrors(['application_id']);

            $this->assertSame($application->id, $coverLetter->fresh()->application_id);
        }

        $this->actingAs($this->coverLetterOnly)
            ->put(route('admin.cover-letters.update', $coverLetter), ['position' => 'Staff Engineer'] + $payload)
            ->assertSessionHasNoErrors();

        $this->assertSame('Staff Engineer', $coverLetter->fresh()->position);
        $this->assertSame($application->id, $coverLetter->fresh()->application_id);
    }

    public function test_cover_letter_without_an_application_still_saves_for_that_user(): void
    {
        $this->actingAs($this->coverLetterOnly)
            ->post(route('admin.cover-letters.store'), $this->coverLetterPayload())
            ->assertSessionHasNoErrors();

        $this->assertNull(CoverLetter::query()->where('company_name', 'Hardening Check Co')->firstOrFail()->application_id);
    }

    public function test_discard_is_refused_for_the_resume_of_a_deleted_application_that_was_applied_to(): void
    {
        $application = Application::factory()->withTargetedResume()->applied()->create();
        $targetedResume = $application->targetedResume;
        app(ApplicationService::class)->delete($application);

        try {
            app(ApplicationService::class)->discardTargetedResume($targetedResume->fresh());
            $this->fail('The resume of a deleted, applied application was discarded.');
        } catch (TargetedResumeAlreadySentException) {
            $this->assertTrue(TargetedResume::query()->whereKey($targetedResume->id)->exists());
        }

        $this->actingAs($this->resumeEditor)
            ->deleteJson(route('admin.resume.targeted.destroy', $targetedResume))
            ->assertStatus(409);

        $this->assertTrue(TargetedResume::query()->whereKey($targetedResume->id)->exists());
        $this->assertSame($targetedResume->id, Application::withTrashed()->findOrFail($application->id)->targeted_resume_id);
    }

    public function test_discarding_the_resume_of_a_deleted_unapplied_application_returns_to_the_list(): void
    {
        $application = Application::factory()->withTargetedResume()->create();
        $targetedResume = $application->targetedResume;
        app(ApplicationService::class)->delete($application);

        $this->actingAs($this->resumeEditor)
            ->delete(route('admin.resume.targeted.destroy', $targetedResume))
            ->assertRedirect(route('admin.resume.targeted.index'));

        $this->assertFalse(TargetedResume::query()->whereKey($targetedResume->id)->exists());
        $this->assertNull(Application::withTrashed()->findOrFail($application->id)->targeted_resume_id);
    }

    public function test_a_database_failure_while_finalizing_is_not_shown_to_the_browser(): void
    {
        $application = Application::factory()->withConversation()->create();
        $failure = new QueryException(
            'mysql',
            'update `targeted_resumes` set `docx_path` = ?',
            ['/var/www/secret/path.docx'],
            new \PDOException("SQLSTATE[22001]: Data too long for column 'docx_path'"),
        );

        $this->mock(TargetedResumeService::class, function ($mock) use ($failure): void {
            $mock->shouldReceive('saveTailoredResume')->andThrow($failure);
            $mock->shouldReceive('saveCoverLetter')->andThrow($failure);
        });

        foreach ([
            ['admin.resume.applications.finalize', ['tailored_content' => '# Summary']],
            ['admin.resume.applications.finalize-cover-letter', ['cover_letter_content' => 'Dear team,']],
        ] as [$route, $payload]) {
            $response = $this->actingAs($this->resumeEditor)->postJson(route($route, $application), $payload);

            $response->assertStatus(422)->assertJsonPath('success', false);
            $this->assertStringNotContainsString('SQLSTATE', $response->json('message'));
            $this->assertStringNotContainsString('docx_path', $response->json('message'));
            $this->assertStringNotContainsString('/var/www', $response->json('message'));
        }
    }

    public function test_a_refusal_the_service_explains_is_still_shown(): void
    {
        $application = Application::factory()->withConversation()->create();

        $this->mock(TargetedResumeService::class, function ($mock): void {
            $mock->shouldReceive('saveTailoredResume')->andThrow(new \RuntimeException('Could not read a resume from that content.'));
        });

        $this->actingAs($this->resumeEditor)
            ->postJson(route('admin.resume.applications.finalize', $application), ['tailored_content' => '# Summary'])
            ->assertStatus(422)
            ->assertExactJson(['success' => false, 'message' => 'Could not read a resume from that content.']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function coverLetterPayload(array $overrides = []): array
    {
        return $overrides + [
            'resume_version_id' => ResumeVersion::factory()->create(['is_current' => true])->id,
            'company_name' => 'Hardening Check Co',
            'position' => 'Engineer',
            'date' => now()->toDateString(),
            'company_address' => "1 Main Street\nCity, ST 12345",
            'greeting' => 'Dear Hiring Manager,',
            'message_body' => 'I am writing to apply.',
            'closing' => 'Sincerely,',
            'signature' => 'Jason Vertucio',
        ];
    }
}
