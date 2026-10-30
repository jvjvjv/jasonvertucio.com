<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\AdminNavigationService;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DesignReferenceTest extends TestCase
{
    use DatabaseTransactions;

    private function userWith(string ...$permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
            $user->givePermissionTo($permission);
        }

        return $user;
    }

    public function test_a_permitted_user_can_open_it(): void
    {
        $this->actingAs($this->userWith('manage-unauthenticated-viewers', 'edit-settings'))
            ->get('/admin/design-reference')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('DesignReference', false));
    }

    public function test_it_receives_only_what_every_admin_page_receives(): void
    {
        $user = $this->userWith('manage-unauthenticated-viewers', 'edit-settings');
        $sharedProps = fn (Assert $page): array => array_keys($page->toArray()['props']);
        $dashboardProps = [];
        $referenceProps = [];

        $this->actingAs($user)->get('/admin')->assertInertia(function (Assert $page) use (&$dashboardProps, $sharedProps) {
            $dashboardProps = $sharedProps($page);
            $page->etc();
        });
        $this->actingAs($user)->get('/admin/design-reference')->assertInertia(function (Assert $page) use (&$referenceProps, $sharedProps) {
            $referenceProps = $sharedProps($page);
            $page->etc();
        });

        $this->assertContains('navBlocks', $dashboardProps);
        $this->assertEqualsCanonicalizing(array_values(array_diff($dashboardProps, ['navBlocks'])), $referenceProps);
    }

    public function test_it_refuses_a_user_without_edit_settings(): void
    {
        $this->actingAs($this->userWith('manage-unauthenticated-viewers'))
            ->get('/admin/design-reference')
            ->assertForbidden();
    }

    public function test_it_redirects_guests_to_login(): void
    {
        $this->get('/admin/design-reference')->assertRedirect('/login');
    }

    public function test_nothing_in_the_navigation_or_on_a_hub_links_to_it(): void
    {
        $user = $this->userWith(
            'manage-unauthenticated-viewers',
            'edit-resume',
            'edit-settings',
            'manage-ai-tools',
            'view-roles',
            'manage-blog',
        );
        $navigation = app(AdminNavigationService::class);
        $appBar = $navigation->getAppBarItems($user);
        $hrefs = [
            ...array_column($appBar, 'href'),
            ...array_merge(...array_map(fn (array $item): array => array_column($item['children'], 'href'), $appBar)),
        ];

        foreach (array_column($appBar, 'href') as $route) {
            array_push($hrefs, ...array_column($navigation->getNavBlocksForRoute($route, $user), 'href'));
        }

        $this->assertNotContains('/admin/design-reference', $hrefs);
    }
}
