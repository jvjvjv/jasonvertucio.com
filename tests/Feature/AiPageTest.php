<?php

namespace Tests\Feature;

use App\Mcp\Tools\PublicResumeDataTool;
use App\Models\McpCall;
use App\Models\ResumeVersion;
use App\Services\Mcp\PublicToolDocumentation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\File;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Description as ToolDescription;
use Laravel\Mcp\Server\Attributes\Name as ServerName;
use Laravel\Mcp\Server\Attributes\Name as ToolName;
use Laravel\Mcp\Server\Tool;
use Tests\TestCase;

/**
 * The public AI page: its narrative comes from a document, its endpoint
 * documentation comes from the server itself.
 */
class AiPageTest extends TestCase
{
    use DatabaseTransactions;

    private string $documentPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->documentPath = resource_path('content/ai.md');
    }

    public function test_the_page_is_publicly_reachable(): void
    {
        $this->get('/ai')
            ->assertOk()
            ->assertSee('Querying this site', false);
    }

    public function test_the_page_is_not_swallowed_by_the_chat_bot_wildcard(): void
    {
        $route = app('router')->getRoutes()->match(
            \Illuminate\Http\Request::create('/ai', 'GET')
        );

        $this->assertSame('ai', $route->getName());
        $this->assertStringContainsString('AiPageController', $route->getActionName());
    }

    public function test_editing_the_document_changes_the_page_without_a_code_change(): void
    {
        $original = File::get($this->documentPath);

        try {
            File::put($this->documentPath, "# AI\n\nA sentence that only this test writes.\n");

            $this->get('/ai')
                ->assertOk()
                ->assertSee('A sentence that only this test writes.', false);
        } finally {
            File::put($this->documentPath, $original);
        }
    }

    public function test_embedded_markup_in_the_document_is_not_rendered(): void
    {
        $original = File::get($this->documentPath);

        try {
            File::put($this->documentPath, "# AI\n\n<script>alert('xss')</script>\n\n[bad](javascript:alert(1))\n");

            $html = $this->get('/ai')->assertOk()->getContent();

            $this->assertStringNotContainsString("alert('xss')", $html);
            $this->assertStringNotContainsString('javascript:alert(1)', $html);
        } finally {
            File::put($this->documentPath, $original);
        }
    }

    public function test_a_missing_document_returns_not_found_rather_than_a_server_error(): void
    {
        $original = File::get($this->documentPath);

        try {
            File::delete($this->documentPath);

            $this->get('/ai')->assertNotFound();
        } finally {
            File::put($this->documentPath, $original);
        }
    }

    public function test_every_registered_tool_is_documented_on_the_page(): void
    {
        $response = $this->get('/ai')->assertOk();

        foreach (app(PublicToolDocumentation::class)->forServer() as $tool) {
            $response->assertSee($tool['name'], false);

            if ($tool['description']) {
                $response->assertSee(e($tool['description']), false);
            }
        }
    }

    public function test_the_roster_reader_returns_every_registered_tool(): void
    {
        $tools = app(PublicToolDocumentation::class)->forServer();

        $names = array_column($tools, 'name');
        sort($names);

        $this->assertSame([
            'get-recent-blog-posts',
            'get-resume-data',
            'get-site-info',
        ], $names);
    }

    public function test_a_tool_added_to_a_roster_documents_itself(): void
    {
        $tools = app(PublicToolDocumentation::class)->forServer(AiPageTestServerWithExtraTool::class);

        $byName = collect($tools)->keyBy('name');

        // The point of the test: a tool the page has never heard of is
        // documented purely by being on the roster, with no edit to the view
        // or to the narrative document.
        $this->assertArrayHasKey('invented-test-tool', $byName);
        $this->assertSame('A tool that exists only in this test.', $byName['invented-test-tool']['description']);
        $this->assertSame(['subject'], array_column($byName['invented-test-tool']['arguments'], 'name'));
        $this->assertTrue($byName['invented-test-tool']['arguments'][0]['required']);

        $this->assertArrayHasKey('get-resume-data', $byName);
    }

    public function test_a_tool_absent_from_a_roster_is_not_documented(): void
    {
        $tools = app(PublicToolDocumentation::class)->forServer(AiPageTestServerWithOneTool::class);

        $this->assertSame(['get-resume-data'], array_column($tools, 'name'));
    }

    public function test_the_page_reports_each_tools_arguments(): void
    {
        $tools = app(PublicToolDocumentation::class)->forServer();

        $byName = collect($tools)->keyBy('name');

        $this->assertSame(['revision_number'], array_column($byName['get-resume-data']['arguments'], 'name'));
        $this->assertSame(['limit', 'search'], array_column($byName['get-recent-blog-posts']['arguments'], 'name'));

        // A tool with no arguments serializes `properties` as an empty object,
        // which arrives as stdClass rather than an array.
        $this->assertSame([], $byName['get-site-info']['arguments']);
    }

    public function test_rendering_the_page_runs_no_tool_handler(): void
    {
        ResumeVersion::factory()->create(['is_current' => true]);
        McpCall::query()->delete();

        $this->get('/ai')->assertOk();

        $this->assertSame(0, McpCall::query()->count(), 'Rendering the page must not invoke a tool.');
    }

    public function test_the_page_carries_no_credential(): void
    {
        $html = $this->get('/ai')->assertOk()->getContent();

        $this->assertDoesNotMatchRegularExpression('/\b\d+\|[A-Za-z0-9]{20,}\b/', $html);
        $this->assertStringNotContainsString('plainTextToken', $html);
    }

    public function test_the_page_states_the_endpoint_and_both_access_levels(): void
    {
        $response = $this->get('/ai')->assertOk();

        $response->assertSee('/mcp', false);
        $response->assertSee('no credentials', false);
        $response->assertSee('bearer token', false);
    }

    public function test_llms_txt_names_the_page_alongside_the_endpoint(): void
    {
        $contents = File::get(public_path('llms.txt'));

        $this->assertStringContainsString('/ai', $contents);
        $this->assertStringContainsString('/mcp', $contents);
    }

    public function test_structured_data_relates_the_owner_to_the_page(): void
    {
        $version = ResumeVersion::factory()->create(['is_current' => true]);
        $version->personalInfo()->create([
            'name' => 'Jason Vertucio',
            'title' => 'Engineer',
            'email' => 'jason@example.com',
            'phone' => '(555) 123-4567',
        ]);

        $html = $this->get('/')->assertOk()->getContent();

        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);
        $data = json_decode(trim($matches[1]), true);

        $this->assertSame(url('/ai'), $data['mainEntity']['subjectOf']['url']);
        $this->assertArrayNotHasKey('email', $data['mainEntity']);
        $this->assertArrayNotHasKey('telephone', $data['mainEntity']);
    }
}

#[ToolName('invented-test-tool')]
#[ToolDescription('A tool that exists only in this test.')]
class AiPageInventedTool extends Tool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'subject' => $schema->string()
                ->description('What to invent something about.')
                ->required(),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        return Response::structured(['invented' => true]);
    }
}

#[ServerName('Test Server With Extra Tool')]
class AiPageTestServerWithExtraTool extends Server
{
    /** @var array<int, class-string<Tool>> */
    protected array $tools = [
        PublicResumeDataTool::class,
        AiPageInventedTool::class,
    ];
}

#[ServerName('Test Server With One Tool')]
class AiPageTestServerWithOneTool extends Server
{
    /** @var array<int, class-string<Tool>> */
    protected array $tools = [
        PublicResumeDataTool::class,
    ];
}
