<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\CoverLetter;
use App\Models\ResumeVersion;
use App\Models\User;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CoverLetterResumeVersionValidationTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'cover-letter-admin-'.uniqid().'@test.com',
            'password' => bcrypt('password'),
        ]);

        Permission::firstOrCreate(['name' => 'manage-unauthenticated-viewers']);
        $this->admin->givePermissionTo('manage-unauthenticated-viewers');
    }

    public function test_store_requires_resume_version_id(): void
    {
        ResumeVersion::factory()->create(['is_current' => true]);

        $payload = $this->validPayload();
        unset($payload['resume_version_id']);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.cover-letters.store'), $payload);

        $response->assertSessionHasErrors(['resume_version_id']);
        $this->assertDatabaseMissing('cover_letters', [
            'company_name' => $payload['company_name'],
        ]);
    }

    public function test_store_requires_resume_version_id_to_exist(): void
    {
        ResumeVersion::factory()->create(['is_current' => true]);

        $payload = $this->validPayload([
            'resume_version_id' => 999999,
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.cover-letters.store'), $payload);

        $response->assertSessionHasErrors(['resume_version_id']);
        $this->assertDatabaseMissing('cover_letters', [
            'company_name' => $payload['company_name'],
        ]);
    }

    public function test_update_requires_resume_version_id(): void
    {
        $version = ResumeVersion::factory()->create(['is_current' => true]);

        $coverLetter = CoverLetter::create($this->validPayload([
            'resume_version_id' => $version->id,
            'company_name' => 'Existing Company',
        ]));

        $payload = $this->validPayload([
            'company_name' => 'Updated Company',
        ]);
        unset($payload['resume_version_id']);

        $response = $this->actingAs($this->admin)
            ->put(route('admin.cover-letters.update', $coverLetter), $payload);

        $response->assertSessionHasErrors(['resume_version_id']);

        $coverLetter->refresh();
        $this->assertSame('Existing Company', $coverLetter->company_name);
    }

    public function test_update_requires_resume_version_id_to_exist(): void
    {
        $version = ResumeVersion::factory()->create(['is_current' => true]);

        $coverLetter = CoverLetter::create($this->validPayload([
            'resume_version_id' => $version->id,
            'company_name' => 'Existing Company',
        ]));

        $payload = $this->validPayload([
            'resume_version_id' => 999999,
            'company_name' => 'Updated Company',
        ]);

        $response = $this->actingAs($this->admin)
            ->put(route('admin.cover-letters.update', $coverLetter), $payload);

        $response->assertSessionHasErrors(['resume_version_id']);

        $coverLetter->refresh();
        $this->assertSame($version->id, $coverLetter->resume_version_id);
    }

    public function test_edit_page_receives_date_as_html_date_string(): void
    {
        $version = ResumeVersion::factory()->create(['is_current' => true]);

        $coverLetter = CoverLetter::create($this->validPayload([
            'resume_version_id' => $version->id,
            'date' => '2026-05-19',
        ]));

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cover-letters.edit', $coverLetter));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('cover-letters/Edit', false)
            ->where('coverLetter.date', '2026-05-19')
        );
    }

    public function test_preview_page_receives_date_as_html_date_string(): void
    {
        $version = ResumeVersion::factory()->create(['is_current' => true]);

        $coverLetter = CoverLetter::create($this->validPayload([
            'resume_version_id' => $version->id,
            'date' => '2026-05-19',
        ]));

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cover-letters.preview', $coverLetter));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('cover-letters/Preview', false)
            ->where('coverLetter.date', '2026-05-19')
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function validPayload(array $overrides = []): array
    {
        $base = [
            'resume_version_id' => ResumeVersion::factory()->create(['is_current' => true])->id,
            'company_name' => 'Acme Corp',
            'position' => 'Senior Software Engineer',
            'date' => now()->toDateString(),
            'company_address' => "123 Main Street\nCity, ST 12345",
            'greeting' => 'Dear Hiring Manager,',
            'message_body' => 'I am excited to apply for this role.',
            'closing' => 'Sincerely,',
            'signature' => 'Jason Vertucio',
        ];

        return array_merge($base, $overrides);
    }

    public function test_create_page_names_the_application_it_was_opened_from(): void
    {
        $application = Application::factory()->create([
            'company_name' => 'Linked Company',
            'position' => 'Linked Position',
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.cover-letters.create', ['application' => $application->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('cover-letters/Create', false)
                ->where('application', [
                    'id' => $application->id,
                    'company_name' => 'Linked Company',
                    'position' => 'Linked Position',
                ])
            );
    }

    public function test_create_page_ignores_a_missing_unknown_or_deleted_application(): void
    {
        $deleted = Application::factory()->create();
        $deleted->delete();

        foreach ([[], ['application' => 999999999], ['application' => $deleted->id], ['application' => 'abc']] as $query) {
            $this->actingAs($this->admin)
                ->get(route('admin.cover-letters.create', $query))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('cover-letters/Create', false)
                    ->where('application', null)
                );
        }
    }

    public function test_store_links_the_letter_to_an_application(): void
    {
        $version = ResumeVersion::factory()->create(['is_current' => true]);
        $application = Application::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.cover-letters.store'), $this->validPayload([
                'resume_version_id' => $version->id,
                'application_id' => $application->id,
                'company_name' => 'Linked Letter Company',
            ]))
            ->assertSessionHasNoErrors();

        $coverLetter = CoverLetter::query()->where('company_name', 'Linked Letter Company')->firstOrFail();

        $this->assertSame($application->id, $coverLetter->application_id);
        $this->assertSame($version->id, $coverLetter->resume_version_id);
        $this->assertTrue($application->coverLetters()->whereKey($coverLetter->id)->exists());
    }

    public function test_store_defaults_the_resume_version_to_the_applications(): void
    {
        ResumeVersion::factory()->create(['is_current' => true]);
        $application = Application::factory()->create();

        $payload = $this->validPayload([
            'application_id' => $application->id,
            'company_name' => 'Defaulted Version Company',
        ]);
        unset($payload['resume_version_id']);

        $this->actingAs($this->admin)
            ->post(route('admin.cover-letters.store'), $payload)
            ->assertSessionHasNoErrors();

        $coverLetter = CoverLetter::query()->where('company_name', 'Defaulted Version Company')->firstOrFail();

        $this->assertSame($application->resume_version_id, $coverLetter->resume_version_id);
    }

    public function test_store_without_an_application_saves_a_standalone_letter(): void
    {
        $version = ResumeVersion::factory()->create(['is_current' => true]);

        $this->actingAs($this->admin)
            ->post(route('admin.cover-letters.store'), $this->validPayload([
                'resume_version_id' => $version->id,
                'company_name' => 'Standalone Letter Company',
            ]))
            ->assertSessionHasNoErrors();

        $coverLetter = CoverLetter::query()->where('company_name', 'Standalone Letter Company')->firstOrFail();

        $this->assertNull($coverLetter->application_id);
    }

    public function test_store_rejects_an_unknown_or_deleted_application(): void
    {
        $version = ResumeVersion::factory()->create(['is_current' => true]);
        $deleted = Application::factory()->create();
        $deleted->delete();

        foreach ([999999999, $deleted->id] as $applicationId) {
            $this->actingAs($this->admin)
                ->post(route('admin.cover-letters.store'), $this->validPayload([
                    'resume_version_id' => $version->id,
                    'application_id' => $applicationId,
                    'company_name' => 'Rejected Link Company',
                ]))
                ->assertSessionHasErrors(['application_id']);
        }

        $this->assertDatabaseMissing('cover_letters', ['company_name' => 'Rejected Link Company']);
    }

    public function test_update_without_an_application_id_keeps_the_existing_link(): void
    {
        $version = ResumeVersion::factory()->create(['is_current' => true]);
        $application = Application::factory()->create();

        $coverLetter = CoverLetter::create($this->validPayload([
            'resume_version_id' => $version->id,
            'application_id' => $application->id,
        ]));

        $this->actingAs($this->admin)
            ->put(route('admin.cover-letters.update', $coverLetter), $this->validPayload([
                'resume_version_id' => $version->id,
                'company_name' => 'Renamed Company',
            ]))
            ->assertSessionHasNoErrors();

        $coverLetter->refresh();

        $this->assertSame('Renamed Company', $coverLetter->company_name);
        $this->assertSame($application->id, $coverLetter->application_id);
    }
}
