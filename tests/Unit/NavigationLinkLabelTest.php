<?php

namespace Tests\Unit;

use App\View\Components\Navigation;
use PHPUnit\Framework\TestCase;

/**
 * `resources/config/config.json` is hand-edited data, and `hover` is optional
 * there — the "Log out" entry has never carried one. A missing optional key
 * must degrade the tooltip, not take down every authenticated page render.
 */
class NavigationLinkLabelTest extends TestCase
{
    private function component(): Navigation
    {
        return new Navigation([]);
    }

    public function test_it_prefers_an_explicit_aria_label(): void
    {
        $label = $this->component()->link_label([
            'label' => 'Blog',
            'ariaLabel' => 'Read the blog',
            'hover' => 'Latest posts',
        ]);

        $this->assertSame('Read the blog', $label);
    }

    public function test_it_qualifies_the_label_with_hover_text(): void
    {
        $label = $this->component()->link_label([
            'label' => 'My Profile',
            'hover' => 'View and manage your profile',
        ]);

        $this->assertSame('My Profile: View and manage your profile', $label);
    }

    public function test_it_falls_back_to_the_bare_label_without_hover_text(): void
    {
        $label = $this->component()->link_label([
            'href' => '/logout',
            'label' => 'Log out',
            'can' => 'authenticated',
        ]);

        $this->assertSame('Log out', $label);
    }

    public function test_it_tolerates_a_link_carrying_neither_label_nor_hover(): void
    {
        $this->assertSame('', $this->component()->link_label(['divider' => true]));
    }
}
