<?php

namespace Tests\Feature;

use App\Models\JobUrl;
use App\Models\JobUrlParser;
use App\Models\User;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use BSPDX\Keystone\Models\KeystoneRole as Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Jvjvjv\CodeTalker\Models\AiSystem;
use Jvjvjv\CodeTalker\Services\AiClientFactory;
use Jvjvjv\CodeTalker\Services\ClaudeService;
use Mockery;
use Tests\TestCase;

class JobUrlParseTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;

    protected AiSystem $aiSystem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Test Admin',
            'email' => 'admin-'.uniqid().'@test.com',
            'password' => bcrypt('password'),
        ]);

        Role::firstOrCreate(['name' => 'admin']);
        $this->admin->assignRole('admin');
        Permission::firstOrCreate(['name' => 'edit-resume']);
        $this->admin->givePermissionTo('edit-resume');

        $this->aiSystem = AiSystem::factory()->create(['is_active' => true]);
    }

    public function test_parse_url_requires_authentication(): void
    {
        $response = $this->postJson(route('admin.resume.targeted.parse-url'), [
            'url' => 'https://example.com/job',
            'ai_system_id' => $this->aiSystem->id,
        ]);

        $response->assertUnauthorized();
    }

    public function test_parse_url_validates_required_fields(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.targeted.parse-url'), []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['url', 'ai_system_id']);
    }

    public function test_parse_url_validates_url_format(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.targeted.parse-url'), [
                'url' => 'not-a-url',
                'ai_system_id' => $this->aiSystem->id,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['url']);
    }

    public function test_parse_url_with_active_parser_uses_selectors(): void
    {
        $html = '<html><body><h1 class="job-title">Senior Engineer</h1><span class="company">Acme Corporation</span><div class="description">We are looking for a Senior Engineer to join our team and help build amazing products for our customers worldwide.</div></body></html>';

        Http::fake([
            'example.com/*' => Http::response($html, 200, ['Content-Type' => 'text/html']),
        ]);

        JobUrlParser::factory()->active()->create([
            'domain' => 'example.com',
            'job_title_selector' => '.job-title',
            'company_name_selector' => '.company',
            'job_description_selector' => '.description',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.targeted.parse-url'), [
                'url' => 'https://example.com/jobs/123',
                'ai_system_id' => $this->aiSystem->id,
            ]);

        $response->assertOk();
        $response->assertJsonFragment([
            'job_title' => 'Senior Engineer',
            'company_name' => 'Acme Corporation',
            'job_description' => 'We are looking for a Senior Engineer to join our team and help build amazing products for our customers worldwide.',
            'used_existing_parser' => true,
        ]);
    }

    public function test_confirm_parser_sets_status_active(): void
    {
        $parser = JobUrlParser::factory()->create([
            'domain' => 'example.com',
            'status' => 'inactive',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.targeted.parser.confirm', $parser));

        $response->assertOk();
        $this->assertDatabaseHas('job_url_parsers', [
            'id' => $parser->id,
            'status' => 'active',
        ]);
    }

    public function test_confirm_parser_deactivates_others_for_same_domain(): void
    {
        $existing = JobUrlParser::factory()->active()->create([
            'domain' => 'example.com',
        ]);

        $otherDomain = JobUrlParser::factory()->active()->create([
            'domain' => 'other.com',
        ]);

        $newParser = JobUrlParser::factory()->create([
            'domain' => 'example.com',
            'status' => 'inactive',
        ]);

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.targeted.parser.confirm', $newParser));

        $this->assertDatabaseHas('job_url_parsers', [
            'id' => $newParser->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('job_url_parsers', [
            'id' => $existing->id,
            'status' => 'inactive',
        ]);
        // parsers for other domains must not be affected
        $this->assertDatabaseHas('job_url_parsers', [
            'id' => $otherDomain->id,
            'status' => 'active',
        ]);
    }

    public function test_reject_parser_keeps_inactive(): void
    {
        $parser = JobUrlParser::factory()->create([
            'domain' => 'example.com',
            'status' => 'inactive',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.targeted.parser.reject', $parser), [
                'feedback' => 'Wrong job title extracted',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('job_url_parsers', [
            'id' => $parser->id,
            'status' => 'inactive',
        ]);
    }

    public function test_reparse_validates_feedback(): void
    {
        $parser = JobUrlParser::factory()->create([
            'domain' => 'example.com',
            'html' => '<html><body>Some content</body></html>',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.targeted.parser.reparse', $parser), [
                'ai_system_id' => $this->aiSystem->id,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['feedback']);
    }

    public function test_parse_url_handles_http_timeout(): void
    {
        Http::fake([
            '*' => Http::response('', 500),
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.targeted.parse-url'), [
                'url' => 'https://example.com/jobs/timeout',
                'ai_system_id' => $this->aiSystem->id,
            ]);

        $response->assertUnprocessable();
    }

    public function test_parse_url_handles_non_html_response(): void
    {
        Http::fake([
            '*' => Http::response('{"data": "json"}', 200, ['Content-Type' => 'application/json']),
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.targeted.parse-url'), [
                'url' => 'https://example.com/api/job',
                'ai_system_id' => $this->aiSystem->id,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonFragment(['message' => 'The URL did not return an HTML page.']);
    }

    public function test_parse_url_stores_job_url_when_using_active_parser(): void
    {
        $html = '<html><body>'
            .'<h1 class="job-title">Senior Engineer</h1>'
            .'<span class="company">Acme Corporation</span>'
            .'<div class="location">New York, NY — Hybrid</div>'
            .'<div class="description">We are looking for a Senior Engineer to join our team and help build amazing products for our customers worldwide.</div>'
            .'</body></html>';

        Http::fake([
            'example.com/*' => Http::response($html, 200, ['Content-Type' => 'text/html']),
        ]);

        $parser = JobUrlParser::factory()->active()->create([
            'domain' => 'example.com',
            'job_title_selector' => '.job-title',
            'company_name_selector' => '.company',
            'job_location_selector' => '.location',
            'job_description_selector' => '.description',
        ]);

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.targeted.parse-url'), [
                'url' => 'https://example.com/jobs/123',
                'ai_system_id' => $this->aiSystem->id,
            ]);

        $this->assertDatabaseHas('job_urls', [
            'job_url_parser_id' => $parser->id,
            'url' => 'https://example.com/jobs/123',
        ]);

        $jobUrl = JobUrl::where('job_url_parser_id', $parser->id)->first();
        $this->assertNotNull($jobUrl);

        $contents = json_decode($jobUrl->contents, true);
        $this->assertSame('Senior Engineer', $contents['job_title']);
        $this->assertSame('Acme Corporation', $contents['company_name']);
    }

    public function test_parse_url_stores_job_url_when_using_ai_extraction(): void
    {
        $html = str_repeat('<p>We are a great company hiring talented engineers for our growing team.</p>', 5);

        Http::fake([
            'no-parser.com/*' => Http::response(
                "<html><body>{$html}</body></html>",
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $aiResponse = [
            'content' => [[
                'type' => 'text',
                'text' => json_encode([
                    'job_title' => 'Software Engineer',
                    'company_name' => 'No Parser Corp',
                    'job_location' => 'Remote',
                    'job_description' => 'We are a great company hiring talented engineers.',
                    'job_title_selector' => 'h1',
                    'company_name_selector' => '.company',
                    'job_location_selector' => '.location',
                    'job_description_selector' => 'p',
                    'reasoning' => 'Used semantic HTML elements.',
                ]),
            ]],
        ];

        $client = Mockery::mock(ClaudeService::class);
        $client->shouldReceive('withSystem')->once()->andReturnSelf();
        $client->shouldReceive('withMaxTokens')->once()->andReturnSelf();
        $client->shouldReceive('message')->once()->andReturn($aiResponse);

        $factory = Mockery::mock(AiClientFactory::class);
        $factory->shouldReceive('forSystem')->once()->andReturn($client);

        $this->app->instance(AiClientFactory::class, $factory);

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.targeted.parse-url'), [
                'url' => 'https://no-parser.com/jobs/456',
                'ai_system_id' => $this->aiSystem->id,
            ]);

        $this->assertDatabaseHas('job_urls', [
            'url' => 'https://no-parser.com/jobs/456',
        ]);

        $jobUrl = JobUrl::where('url', 'https://no-parser.com/jobs/456')->first();
        $this->assertNotNull($jobUrl);

        $contents = json_decode($jobUrl->contents, true);
        $this->assertSame('Software Engineer', $contents['job_title']);
        $this->assertSame('No Parser Corp', $contents['company_name']);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
