<?php

namespace Tests\Unit\Services;

use App\Services\AdminNavigationService;
use Tests\TestCase;

class AdminNavigationServiceTest extends TestCase
{
    protected AdminNavigationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AdminNavigationService;
    }

    /**
     * Blade-rendered routes must be flagged so the React layout renders them as
     * plain anchors. An Inertia visit to a non-Inertia route returns HTML with
     * no `x-inertia` header, which Inertia displays in an error-modal iframe
     * instead of navigating.
     */
    public function test_nav_blocks_flag_blade_routes_as_external(): void
    {
        $blocks = $this->service->getNavBlocksForRoute('/admin/resume', null);

        $resumePreview = collect($blocks)->firstWhere('href', '/resume');

        $this->assertNotNull($resumePreview, 'Resume Preview block is missing.');
        $this->assertTrue($resumePreview['external']);
    }

    public function test_nav_blocks_expose_external_on_every_item(): void
    {
        $blocks = $this->service->getNavBlocksForRoute('/admin/resume', null);

        $this->assertNotEmpty($blocks);

        foreach ($blocks as $block) {
            $this->assertArrayHasKey('external', $block);
            $this->assertIsBool($block['external']);
        }
    }

    public function test_app_bar_items_default_inertia_routes_to_not_external(): void
    {
        $items = $this->service->getAppBarItems(null);

        $admin = collect($items)->firstWhere('href', '/admin');

        $this->assertNotNull($admin, 'Admin app bar entry is missing.');
        $this->assertFalse($admin['external']);
    }

    public function test_app_bar_children_expose_external(): void
    {
        $items = $this->service->getAppBarItems(null);

        foreach ($items as $item) {
            $this->assertArrayHasKey('external', $item);

            foreach ($item['children'] as $child) {
                $this->assertArrayHasKey('external', $child);
                $this->assertIsBool($child['external']);
            }
        }
    }

    /**
     * Guards against a Blade route being added to the admin navigation without
     * the flag, which would silently reintroduce the iframe modal.
     */
    public function test_no_unflagged_non_admin_routes_in_navigation(): void
    {
        $config = json_decode(
            file_get_contents(resource_path('js/admin/navigation.json')),
            true
        );

        foreach ($config['navigation'] as $entry) {
            foreach ($entry['navigationItems'] as $item) {
                if (str_starts_with($item['href'], '/admin')) {
                    continue;
                }

                $this->assertTrue(
                    $item['external'] ?? false,
                    "Navigation item {$item['href']} is outside /admin and must be marked \"external\": true."
                );
            }
        }
    }
}
