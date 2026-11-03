<?php

namespace Tests\Feature;

use App\Mcp\Servers\PublicServer;
use App\Mcp\Tools\Concerns\CachesPublicToolResults;
use App\Mcp\Tools\PublicResumeDataTool;
use App\Mcp\Tools\SaveBlogPostTool;
use App\Models\User;
use App\Services\Mcp\Tools\ChatBot\GetResumeDataTool;
use App\Services\Mcp\Tools\ChatBot\GetSiteInfoTool;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Canvas\Enums\Role;
use Canvas\Models\CanvasUser;
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

    /**
     * @return list<string>
     */
    private function rosterNames(): array
    {
        $server = new PublicServer(new FakeTransporter);

        return collect($server->createContext()->tools())
            ->map(fn ($tool): string => $tool->name())
            ->sort()
            ->values()
            ->all();
    }

    public function test_the_public_roster_is_exactly_three_read_only_tools(): void
    {
        $this->assertSame([
            'get-recent-blog-posts',
            'get-resume-data',
            'get-site-info',
        ], $this->rosterNames());
    }

    public function test_a_caller_without_manage_blog_gets_the_same_three_tools(): void
    {
        $this->actingAs(User::factory()->create());

        $this->assertSame([
            'get-recent-blog-posts',
            'get-resume-data',
            'get-site-info',
        ], $this->rosterNames());
    }

    public function test_a_manage_blog_caller_gets_the_post_writing_tool_and_no_other_write_tool(): void
    {
        Permission::firstOrCreate(['name' => 'manage-blog']);
        $user = User::factory()->create();
        $user->givePermissionTo('manage-blog');
        CanvasUser::query()->create(['user_id' => $user->id, 'role' => Role::Admin, 'username' => 'author-'.$user->id]);
        $this->actingAs($user);

        $this->assertSame([
            'get-recent-blog-posts',
            'get-resume-data',
            'get-site-info',
            'save-blog-post',
        ], $this->rosterNames());
    }

    public function test_the_post_writing_tool_lives_outside_code_talkers_discovery_tree(): void
    {
        $path = realpath((string) (new \ReflectionClass(SaveBlogPostTool::class))->getFileName());

        foreach (array_keys(CodeTalkerServiceProvider::toolDirectories()) as $directory) {
            $this->assertStringStartsNotWith(
                realpath($directory).DIRECTORY_SEPARATOR,
                $path,
                "The post-writing tool must never be discoverable by chat bots [{$directory}]."
            );
        }
    }

    public function test_the_post_writing_tool_is_never_cached(): void
    {
        $this->assertNotContains(CachesPublicToolResults::class, class_uses_recursive(SaveBlogPostTool::class));
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
        $this->assertStringNotContainsString(config('canvas.path'), json_encode($content));
    }
}
