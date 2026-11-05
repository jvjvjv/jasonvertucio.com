<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\User;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Applications and Application Metrics live under /admin/employment, with
 * their JSON API under /api/admin/employment/applications. Their former
 * /admin/resume addresses are gone, not redirected.
 */
class EmploymentRoutesTest extends TestCase
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

    public function test_the_pages_are_served_under_employment(): void
    {
        $application = Application::factory()->create();

        $pages = [
            '/admin/employment/applications' => 'employment/applications/Index',
            '/admin/employment/applications/new' => 'employment/applications/Create',
            "/admin/employment/applications/{$application->id}" => 'employment/applications/Show',
            '/admin/employment/metrics?range=90d' => 'employment/metrics/Index',
        ];

        foreach ($pages as $url => $component) {
            $this->actingAs($this->admin)
                ->get($url)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->component($component, false));
        }
    }

    public function test_the_api_is_served_under_employment(): void
    {
        $application = Application::factory()->create();

        $this->assertSame(
            "/api/admin/employment/applications/{$application->id}/pass",
            route('admin.employment.applications.pass', $application, false)
        );

        $this->actingAs($this->admin)
            ->postJson("/api/admin/employment/applications/{$application->id}/pass")
            ->assertOk();
    }

    public function test_the_former_resume_urls_are_gone(): void
    {
        $application = Application::factory()->create();

        foreach ([
            '/admin/resume/applications',
            '/admin/resume/applications/new',
            "/admin/resume/applications/{$application->id}",
            '/admin/resume/metrics',
        ] as $url) {
            $this->actingAs($this->admin)->get($url)->assertNotFound();
        }

        $this->actingAs($this->admin)
            ->postJson('/api/admin/resume/applications', ['intent' => 'applied'])
            ->assertNotFound();
        $this->actingAs($this->admin)
            ->postJson("/api/admin/resume/applications/{$application->id}/pass")
            ->assertNotFound();
    }

    public function test_the_new_urls_require_the_edit_resume_permission(): void
    {
        $application = Application::factory()->create();
        $stranger = User::factory()->create();

        foreach (['/admin/employment/applications', '/admin/employment/metrics'] as $url) {
            $this->actingAs($stranger)->get($url)->assertForbidden();
        }

        $this->actingAs($stranger)
            ->postJson("/api/admin/employment/applications/{$application->id}/pass")
            ->assertForbidden();
    }
}
