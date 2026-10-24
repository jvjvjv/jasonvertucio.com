<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    public function test_privacy_policy_page_renders_the_markdown_source(): void
    {
        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('Privacy Policy', false)
            ->assertSee('The Bootstrap Paradox, LLC', false)
            ->assertSee('info@bspdx.com', false)
            ->assertDontSee('## ', false);
    }

    public function test_terms_page_renders_the_markdown_source(): void
    {
        $this->get(route('legal.terms'))
            ->assertOk()
            ->assertSee('Terms of Service', false)
            ->assertSee('Pennsylvania', false)
            ->assertDontSee('## ', false);
    }

    public function test_markdown_headings_become_html_headings(): void
    {
        $this->get(route('legal.terms'))
            ->assertOk()
            ->assertSee('<h2', false);
    }

    public function test_tables_are_wrapped_in_a_horizontal_scroll_container(): void
    {
        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('<div class="table-scroll"><table>', false)
            ->assertSee('</table></div>', false)
            ->assertSee('<th>', false);
    }

    public function test_hard_breaks_separate_the_effective_and_updated_dates(): void
    {
        $this->get(route('legal.terms'))
            ->assertOk()
            ->assertSee('September 8, 2026<br />', false);
    }

    public function test_home_page_links_to_both_legal_pages(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('href="'.route('legal.privacy').'"', false)
            ->assertSee('href="'.route('legal.terms').'"', false);
    }

    public function test_missing_document_returns_not_found(): void
    {
        $path = resource_path('legal/terms-of-service.md');
        $backup = File::get($path);
        File::delete($path);

        try {
            $this->get(route('legal.terms'))->assertNotFound();
        } finally {
            File::put($path, $backup);
        }
    }

    public function test_legal_routes_are_not_swallowed_by_the_chat_bot_wildcard(): void
    {
        $this->assertSame(
            'legal.privacy',
            app('router')->getRoutes()->match(
                \Illuminate\Http\Request::create('/legal/privacy', 'GET')
            )->getName()
        );
    }
}
