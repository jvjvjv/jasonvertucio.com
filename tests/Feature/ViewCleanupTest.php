<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ViewCleanupTest extends TestCase
{
    public function test_no_layout_loads_alpine_from_a_cdn(): void
    {
        foreach (['layout', 'chat', 'chat-bare'] as $layout) {
            $source = File::get(resource_path("views/{$layout}.blade.php"));

            $this->assertStringNotContainsString('cdn.jsdelivr.net', $source, "{$layout} still loads a CDN script");
            $this->assertStringContainsString('resources/js/app.js', $source, "{$layout} must load the bundle that starts Alpine");
        }
    }

    public function test_alpine_is_started_by_the_bundle(): void
    {
        $bundle = File::get(resource_path('js/app.js'));

        $this->assertStringContainsString("from 'alpinejs'", $bundle);
        $this->assertStringContainsString('Alpine.start()', $bundle);
    }

    public function test_no_view_calls_env(): void
    {
        $offenders = collect(File::allFiles(resource_path('views')))
            ->filter(fn ($file) => str_contains($file->getContents(), 'env('))
            ->map(fn ($file) => $file->getRelativePathname())
            ->values()
            ->all();

        $this->assertSame([], $offenders, 'env() returns null once configuration is cached; use config().');
    }

    public function test_the_post_page_does_not_dump_the_post_as_json(): void
    {
        config(['app.debug' => true]);

        $source = File::get(resource_path('views/blog/single.blade.php'));

        $this->assertStringNotContainsString('json_encode', $source);
    }
}
