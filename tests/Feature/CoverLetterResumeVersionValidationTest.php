<?php

namespace Tests\Feature;

use App\Models\CoverLetter;
use App\Models\ResumeVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CoverLetterResumeVersionValidationTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;

    protected function setUp(): void {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'cover-letter-admin-' . uniqid() . '@test.com',
            'password' => bcrypt('password'),
        ]);

        Permission::findOrCreate('manage-unauthenticated-viewers', 'web');
        $this->admin->givePermissionTo('manage-unauthenticated-viewers');
    }

    public function test_store_requires_resume_version_id(): void {
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

    public function test_store_requires_resume_version_id_to_exist(): void {
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

    public function test_update_requires_resume_version_id(): void {
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

    public function test_update_requires_resume_version_id_to_exist(): void {
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
     * @param array<string, mixed> $overrides
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
}
