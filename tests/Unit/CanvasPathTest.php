<?php

namespace Tests\Unit;

use App\Support\CanvasPath;
use Tests\TestCase;

class CanvasPathTest extends TestCase
{
    public function test_href_is_root_relative_whatever_the_slashes_in_the_setting(): void
    {
        foreach (['journal/admin', '/journal/admin', 'journal/admin/'] as $setting) {
            config(['canvas.path' => $setting]);

            $this->assertSame('/journal/admin', CanvasPath::href());
        }
    }

    public function test_fill_replaces_every_placeholder(): void
    {
        config(['canvas.path' => 'journal/admin']);

        $this->assertSame(
            '{"a":"/journal/admin","b":"/journal/admin"}',
            CanvasPath::fill('{"a":"{canvas.path}","b":"{canvas.path}"}')
        );
    }

    public function test_the_site_config_carries_no_hardcoded_canvas_link(): void
    {
        $this->assertStringContainsString(CanvasPath::PLACEHOLDER, file_get_contents(resource_path('config/config.json')));
        $this->assertStringNotContainsString('"/canvas"', file_get_contents(resource_path('config/config.json')));
    }
}
