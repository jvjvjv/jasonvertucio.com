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

    public function test_the_dashboard_opens_with_employment_and_closes_with_blog(): void
    {
        $user = $this->userWith(...self::EVERY_ADMIN_PERMISSION);

        $this->assertSame([
            '/admin/employment',
            '/admin/mail-preview',
            '/admin/ai',
            '/admin/roles-permissions',
            '/admin/site-settings',
            '/admin/blog',
        ], $this->blockHrefs('/admin', $user));
    }

    public function test_the_dashboard_no_longer_links_to_the_grouped_pages(): void
    {
        $hrefs = $this->blockHrefs('/admin', $this->userWith(...self::EVERY_ADMIN_PERMISSION));

        foreach (['/admin/resume/applications', '/admin/resume/metrics', '/admin/resume', '/admin/cover-letters', '/canvas', '/admin/comments'] as $moved) {
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

    public function test_the_blog_hub_shows_both_tiles_to_a_blog_administrator(): void
    {
        $this->actingAs($this->userWith('manage-blog'))
            ->get('/admin/blog')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('blog/Hub', false)
                ->where('navBlocks.0.href', '/canvas')
                ->where('navBlocks.0.external', true)
                ->where('navBlocks.1.href', '/admin/comments')
                ->has('navBlocks', 2));
    }

    public function test_the_blog_hub_shows_only_comments_to_a_moderator(): void
    {
        $this->actingAs($this->userWith('manage-comments'))
            ->get('/admin/blog')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('navBlocks.0.href', '/admin/comments')
                ->has('navBlocks', 1));
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

    public function test_the_app_bar_lists_employment_first_after_the_dashboard(): void
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
