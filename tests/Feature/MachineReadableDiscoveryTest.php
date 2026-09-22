<?php

namespace Tests\Feature;

use App\Models\ResumeVersion;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * What the site tells machines about itself.
 */
class MachineReadableDiscoveryTest extends TestCase
{
    use DatabaseTransactions;

    private function liveVersion(string $title = 'Lead Front-End Engineer'): ResumeVersion
    {
        $version = ResumeVersion::factory()->create(['is_current' => true]);
        $version->personalInfo()->create([
            'name' => 'Jason Vertucio',
            'title' => $title,
            'email' => 'jason@example.com',
            'phone' => '(555) 123-4567',
            'linkedin' => 'linkedin.com/in/jasonvertucio',
            'url' => 'https://jasonvertucio.com',
            'summary' => 'Builds things that last.',
        ]);

        return $version;
    }

    /**
     * @return array<string, mixed>
     */
    private function structuredDataFrom(string $html): array
    {
        $matched = preg_match(
            '#<script type="application/ld\+json">(.*?)</script>#s',
            $html,
            $matches
        );

        $this->assertSame(1, $matched, 'The homepage should carry a JSON-LD block.');

        $decoded = json_decode(trim($matches[1]), true);

        $this->assertIsArray($decoded, 'The JSON-LD block should be valid JSON.');

        return $decoded;
    }

    public function test_the_homepage_publishes_structured_identity_data(): void
    {
        $this->liveVersion();

        $data = $this->structuredDataFrom($this->get('/')->assertOk()->getContent());

        $this->assertSame('ProfilePage', $data['@type']);
        $this->assertSame('Person', $data['mainEntity']['@type']);
        $this->assertSame('Jason Vertucio', $data['mainEntity']['name']);
        $this->assertSame('Lead Front-End Engineer', $data['mainEntity']['jobTitle']);
        $this->assertSame('Builds things that last.', $data['mainEntity']['description']);
        $this->assertSame('https://jasonvertucio.com', $data['mainEntity']['url']);
    }

    public function test_structured_data_carries_no_contact_details(): void
    {
        $this->liveVersion();

        $html = $this->get('/')->assertOk()->getContent();
        $data = $this->structuredDataFrom($html);

        $this->assertArrayNotHasKey('email', $data['mainEntity']);
        $this->assertArrayNotHasKey('telephone', $data['mainEntity']);

        $serialized = json_encode($data);
        $this->assertStringNotContainsString('jason@example.com', $serialized);
        $this->assertStringNotContainsString('555', $serialized);
    }

    public function test_structured_data_tracks_its_source(): void
    {
        $this->liveVersion('Original Title');

        $data = $this->structuredDataFrom($this->get('/')->assertOk()->getContent());
        $this->assertSame('Original Title', $data['mainEntity']['jobTitle']);

        ResumeVersion::query()->update(['is_current' => false]);
        $this->liveVersion('Changed Title');

        $html = $this->get('/')->assertOk()->getContent();
        $data = $this->structuredDataFrom($html);

        $this->assertSame('Changed Title', $data['mainEntity']['jobTitle']);
        $this->assertStringContainsString('Changed Title', $html, 'The rendered page should change with it.');
    }

    public function test_the_language_model_description_is_served_and_names_the_endpoint(): void
    {
        $path = public_path('llms.txt');

        $this->assertFileExists($path);

        $contents = file_get_contents($path);

        $this->assertStringContainsString('/mcp', $contents);
        $this->assertStringContainsString('get-resume-data', $contents);
        $this->assertStringContainsString('get-recent-blog-posts', $contents);
        $this->assertStringContainsString('get-site-info', $contents);
    }

    public function test_the_language_model_description_explains_the_access_levels(): void
    {
        $contents = file_get_contents(public_path('llms.txt'));

        $this->assertMatchesRegularExpression('/none required|no credentials|anonymously/i', $contents);
        $this->assertStringContainsString('bearer token', $contents);
    }

    public function test_crawler_directives_do_not_disallow_the_discovery_surfaces(): void
    {
        $robots = file_get_contents(public_path('robots.txt'));

        $this->assertDoesNotMatchRegularExpression('#^\s*Disallow:\s*/\s*$#mi', $robots);
        $this->assertStringNotContainsString('Disallow: /mcp', $robots);
        $this->assertStringNotContainsString('Disallow: /llms.txt', $robots);
    }
}
