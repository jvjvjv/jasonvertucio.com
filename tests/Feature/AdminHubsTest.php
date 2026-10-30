<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AdminNavigationService;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminHubsTest extends TestCase
{
    use DatabaseTransactions;

    private const array EVERY_ADMIN_PERMISSION = [
        'manage-unauthenticated-viewers',
        'edit-resume',
        'edit-settings',
        'manage-ai-tools',
        'view-roles',
        'manage-blog',
    ];

    private function userWith(string ...$permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
            $user->givePermissionTo($permission);
        }

        return $user;
    }

    /**
     * @return list<string>
     */
    private function blockHrefs(string $route, User $user): array
    {
        return array_column(app(AdminNavigationService::class)->getNavBlocksForRoute($route, $user), 'href');
    }

    public function test_the_dashboard_lists_blog_employment_ai_tools_and_site_settings(): void
    {
        $user = $this->userWith(...self::EVERY_ADMIN_PERMISSION);

        $this->assertSame([
            '/admin/blog',
            '/admin/employment',
            '/admin/ai',
            '/admin/settings',
        ], $this->blockHrefs('/admin', $user));
    }

    public function test_the_design_reference_is_not_a_dashboard_tile(): void
    {
        $this->assertNotContains(
            '/admin/design-reference',
            $this->blockHrefs('/admin', $this->userWith(...self::EVERY_ADMIN_PERMISSION))
        );
    }

    public function test_the_dashboard_no_longer_links_to_the_grouped_pages(): void
    {
        $hrefs = $this->blockHrefs('/admin', $this->userWith(...self::EVERY_ADMIN_PERMISSION));

        foreach (['/admin/resume/applications', '/admin/resume/metrics', '/admin/resume', '/admin/cover-letters', '/admin/blog/canvas', '/admin/comments', '/admin/mail-preview', '/admin/site-settings', '/admin/roles-permissions'] as $moved) {
            $this->assertNotContains($moved, $hrefs);
        }
    }

    public function test_the_employment_hub_lists_its_tiles_in_order(): void
    {
        $user = $this->userWith('manage-unauthenticated-viewers', 'edit-resume');

        $this->actingAs($user)
            ->get('/admin/employment')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('employment/Hub', false)
                ->where('navBlocks.0.href', '/admin/resume/applications')
                ->where('navBlocks.1.href', '/admin/resume/metrics')
                ->where('navBlocks.2.href', '/admin/resume')
                ->where('navBlocks.3.href', '/admin/cover-letters')
                ->where('navBlocks.4.href', '/admin/employment/company-intel')
                ->has('navBlocks', 5));
    }

    public function test_the_employment_hub_hides_tiles_the_user_may_not_open(): void
    {
        $this->actingAs($this->userWith('manage-unauthenticated-viewers'))
            ->get('/admin/employment')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('navBlocks', 0));
    }

    public function test_the_employment_hub_redirects_guests_to_login(): void
    {
        $this->get('/admin/employment')->assertRedirect('/login');
    }

    public function test_the_blog_hub_shows_every_tile_to_a_blog_administrator(): void
    {
        $this->actingAs($this->userWith('manage-blog'))
            ->get('/admin/blog')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('blog/Hub', false)
                ->where('navBlocks.0.href', '/admin/blog/canvas')
                ->where('navBlocks.0.external', true)
                ->where('navBlocks.1.href', '/admin/comments')
                ->where('navBlocks.2.href', '/blog')
                ->where('navBlocks.2.external', true)
                ->has('navBlocks', 3));
    }

    public function test_the_blog_admin_tile_follows_the_configured_canvas_path(): void
    {
        $user = $this->userWith('manage-blog');

        foreach (['journal/admin', '/journal/admin/'] as $path) {
            config(['canvas.path' => $path]);

            $this->assertSame(
                '/journal/admin',
                app(AdminNavigationService::class)->getNavBlocksForRoute('/admin/blog', $user)[0]['href']
            );
        }
    }

    public function test_the_blog_hub_shows_comments_and_the_public_blog_to_a_moderator(): void
    {
        $this->actingAs($this->userWith('manage-comments'))
            ->get('/admin/blog')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('navBlocks.0.href', '/admin/comments')
                ->where('navBlocks.1.href', '/blog')
                ->has('navBlocks', 2));
    }

    public function test_the_blog_hub_refuses_a_user_without_blog_permissions(): void
    {
        $this->actingAs($this->userWith('edit-resume'))
            ->get('/admin/blog')
            ->assertForbidden();
    }

    public function test_the_app_bar_leaves_out_resume_management(): void
    {
        $items = app(AdminNavigationService::class)->getAppBarItems($this->userWith('edit-resume'));

        $this->assertNotContains('/admin/resume', array_column($items, 'href'));
        $this->assertContains('/admin/resume', array_column($items[1]['children'], 'href'));
    }

    public function test_the_navigation_lists_the_hubs_in_dashboard_order(): void
    {
        $user = $this->userWith(...self::EVERY_ADMIN_PERMISSION);

        $this->assertSame(
            ['/admin', ...$this->blockHrefs('/admin', $user)],
            array_column(app(AdminNavigationService::class)->getAppBarItems($user), 'href')
        );
    }

    public function test_the_site_settings_hub_lists_its_tiles_in_order(): void
    {
        $this->actingAs($this->userWith('edit-settings', 'view-roles'))
            ->get('/admin/settings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('site-settings/Hub', false)
                ->where('navBlocks.0.href', '/admin/mail-preview')
                ->where('navBlocks.1.href', '/admin/site-settings')
                ->where('navBlocks.2.href', '/admin/roles-permissions')
                ->has('navBlocks', 3));
    }

    public function test_the_site_settings_hub_shows_only_settings_tiles_without_view_roles(): void
    {
        $this->actingAs($this->userWith('edit-settings'))
            ->get('/admin/settings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('navBlocks.0.href', '/admin/mail-preview')
                ->where('navBlocks.1.href', '/admin/site-settings')
                ->has('navBlocks', 2));
    }

    public function test_the_site_settings_hub_shows_only_roles_without_edit_settings(): void
    {
        $this->actingAs($this->userWith('view-roles'))
            ->get('/admin/settings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('navBlocks.0.href', '/admin/roles-permissions')
                ->has('navBlocks', 1));
    }

    public function test_the_site_settings_hub_refuses_a_user_without_settings_permissions(): void
    {
        $this->actingAs($this->userWith('edit-resume'))
            ->get('/admin/settings')
            ->assertForbidden();
    }

    public function test_the_site_settings_hub_redirects_guests_to_login(): void
    {
        $this->get('/admin/settings')->assertRedirect('/login');
    }

    public function test_the_navigation_lists_site_settings_with_its_tiles_as_pages(): void
    {
        $items = app(AdminNavigationService::class)->getAppBarItems($this->userWith('edit-settings', 'view-roles'));

        $this->assertSame('/admin/settings', $items[1]['href']);
        $this->assertSame(
            ['/admin/mail-preview', '/admin/site-settings', '/admin/roles-permissions'],
            array_column($items[1]['children'], 'href')
        );
    }

    public function test_the_navigation_lists_employment_first_for_a_user_without_blog_access(): void
    {
        $items = app(AdminNavigationService::class)->getAppBarItems($this->userWith('edit-resume'));

        $this->assertSame('/admin', $items[0]['href']);
        $this->assertSame('/admin/employment', $items[1]['href']);
        $this->assertSame([
            '/admin/resume/applications',
            '/admin/resume/metrics',
            '/admin/resume',
            '/admin/cover-letters',
            '/admin/employment/company-intel',
        ], array_column($items[1]['children'], 'href'));
    }
}
