<?php

namespace Tests\Feature;

use App\Models\ResumeVersion;
use App\Models\User;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ResumeDownloadVisibilityTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Save User',
            'email' => 'resume-download-visibility-'.uniqid().'@test.com',
            'password' => bcrypt('password'),
        ]);

        Permission::firstOrCreate(['name' => 'save-resume']);
        Permission::firstOrCreate(['name' => 'read-resume']);
        $this->user->givePermissionTo('save-resume');
        $this->user->givePermissionTo('read-resume');
    }

    public function test_resume_page_shows_download_options_with_no_cached_file(): void
    {
        ResumeVersion::factory()->create(['is_current' => true, 'docx_path' => null, 'pdf_path' => null]);

        $response = $this->actingAs($this->user)->get(route('resume.index'));

        $response->assertOk();
        $response->assertSee(route('resume.download.docx'), false);
        $response->assertSee(route('resume.download.pdf'), false);
    }

    public function test_resume_page_shows_no_download_options_without_a_live_version(): void
    {
        $response = $this->actingAs($this->user)->get(route('resume.index'));

        $response->assertOk();
        $response->assertDontSee(route('resume.download.docx'), false);
        $response->assertDontSee(route('resume.download.pdf'), false);
    }

    public function test_download_page_shows_download_links_with_no_cached_file(): void
    {
        ResumeVersion::factory()->create(['is_current' => true, 'docx_path' => null, 'pdf_path' => null]);

        $response = $this->actingAs($this->user)->get(route('resume.download.index'));

        $response->assertOk();
        $response->assertSee(route('resume.download.docx'), false);
        $response->assertSee(route('resume.download.pdf'), false);
        $response->assertDontSee('No resume files are currently available for download.');
    }

    public function test_download_page_shows_no_files_available_without_a_live_version(): void
    {
        $response = $this->actingAs($this->user)->get(route('resume.download.index'));

        $response->assertOk();
        $response->assertSee('No resume files are currently available for download.');
    }
}
