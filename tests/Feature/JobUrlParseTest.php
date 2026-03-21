<?php

namespace Tests\Feature;

use App\Models\AiSystem;
use App\Models\JobUrlParser;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
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
            'email' => 'admin-' . uniqid() . '@test.com',
            'password' => bcrypt('password'),
        ]);

        Role::findOrCreate('admin', 'web');
        $this->admin->assignRole('admin');
        Permission::findOrCreate('edit-resume', 'web');
        $this->admin->givePermissionTo('edit-resume');

        $this->aiSystem = AiSystem::factory()->create(['is_active' => true]);
    }

    public function testParseUrlRequiresAuthentication(): void
    {
        $response = $this->postJson(route('admin.resume.targeted.parse-url'), [
            'url' => 'https://example.com/job',
            'ai_system_id' => $this->aiSystem->id,
        ]);

        $response->assertUnauthorized();
    }

    public function testParseUrlValidatesRequiredFields(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.targeted.parse-url'), []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['url', 'ai_system_id']);
    }

    public function testParseUrlValidatesUrlFormat(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.targeted.parse-url'), [
                'url' => 'not-a-url',
                'ai_system_id' => $this->aiSystem->id,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['url']);
    }

    public function testParseUrlWithActiveParserUsesSelectors(): void
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

    public function testConfirmParserSetsStatusActive(): void
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

    public function testConfirmParserDeactivatesOthersForSameDomain(): void
    {
        $existing = JobUrlParser::factory()->active()->create([
            'domain' => 'example.com',
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
    }

    public function testRejectParserKeepsInactive(): void
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

    public function testReparseValidatesFeedback(): void
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

    public function testParseUrlHandlesHttpTimeout(): void
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

    public function testParseUrlHandlesNonHtmlResponse(): void
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
}
