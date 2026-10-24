<?php

namespace Tests\Feature;

use App\Mcp\Servers\PublicServer;
use App\Mcp\Tools\PublicResumeDataTool;
use App\Services\Mcp\Tools\ChatBot\GetResumeDataTool;
use App\Services\Mcp\Tools\ChatBot\GetSiteInfoTool;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\File;
use Jvjvjv\CodeTalker\CodeTalkerServiceProvider;
use Laravel\Mcp\Server\Testing\TestResponse;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use Tests\TestCase;

/**
 * The boundary between what the public endpoint serves and what the chat loop
 * discovers — two consumers of the same tool classes that must not bleed into
 * each other.
 */
class PublicMcpRosterBoundaryTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @return array<string, mixed>
     */
    private function structured(TestResponse $response): array
    {
        return (fn (): array => $this->response->toArray()['result']['structuredContent'] ?? [])->call($response);
    }

    public function test_the_redacting_subclass_lives_outside_code_talkers_discovery_tree(): void
    {
        $subclassPath = (new \ReflectionClass(PublicResumeDataTool::class))->getFileName();

        $this->assertIsString($subclassPath);

        foreach (array_keys(CodeTalkerServiceProvider::toolDirectories()) as $directory) {
            $this->assertStringStartsNotWith(
                realpath($directory).DIRECTORY_SEPARATOR,
                realpath($subclassPath),
                "The endpoint-only resume tool must not sit inside a directory CodeTalker discovers [{$directory}]."
            );
        }
    }

    public function test_only_one_discoverable_tool_answers_to_get_resume_data(): void
    {
        $matches = [];

        foreach (CodeTalkerServiceProvider::toolDirectories() as $directory => $namespacePrefix) {
            if (! File::isDirectory($directory)) {
                continue;
            }

            foreach (File::allFiles($directory) as $file) {
                $relative = str_replace([$directory.DIRECTORY_SEPARATOR, '.php'], '', $file->getPathname());
                $class = rtrim($namespacePrefix, '\\').'\\'.str_replace(DIRECTORY_SEPARATOR, '\\', $relative);

                if (! class_exists($class)) {
                    continue;
                }

                $reflection = new \ReflectionClass($class);

                if ($reflection->isAbstract() || ! $reflection->isSubclassOf(\Laravel\Mcp\Server\Tool::class)) {
                    continue;
                }

                $name = $reflection->getAttributes(\Laravel\Mcp\Server\Attributes\Name::class)[0] ?? null;

                if ($name !== null && $name->newInstance()->value === 'get-resume-data') {
                    $matches[] = $class;
                }
            }
        }

        $this->assertSame([GetResumeDataTool::class], $matches);
    }

    public function test_the_public_roster_is_exactly_three_read_only_tools(): void
    {
        $server = new PublicServer(new FakeTransporter);

        $names = collect($server->createContext()->tools())
            ->map(fn ($tool): string => $tool->name())
            ->sort()
            ->values()
            ->all();

        $this->assertSame([
            'get-recent-blog-posts',
            'get-resume-data',
            'get-site-info',
        ], $names);
    }

    public function test_site_info_returns_only_already_public_keys(): void
    {
        $content = $this->structured(
            PublicServer::tool(GetSiteInfoTool::class)->assertOk()
        );

        $this->assertSame(['html_title', 'projects', 'interests'], array_keys($content));
    }

    public function test_site_info_does_not_return_navigation_or_admin_links(): void
    {
        $content = $this->structured(
            PublicServer::tool(GetSiteInfoTool::class)->assertOk()
        );

        $this->assertArrayNotHasKey('links', $content);
        $this->assertStringNotContainsString('/admin', json_encode($content));
        $this->assertStringNotContainsString('/canvas', json_encode($content));
    }
}
