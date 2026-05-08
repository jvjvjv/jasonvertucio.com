<?php

namespace Tests\Feature\Services;

use App\Models\CoverLetter;
use App\Models\ResumePersonalInfo;
use App\Models\ResumeVersion;
use App\Services\CoverLetterDocumentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CoverLetterDocumentServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function testBuildDocxDataIncludesUrlFromPersonalInfo(): void
    {
        $resumeVersion = ResumeVersion::factory()->create();
        ResumePersonalInfo::factory()->create([
            'version_id' => $resumeVersion->id,
            'url' => 'https://jasonvertucio.com',
        ]);

        $coverLetter = CoverLetter::create([
            'resume_version_id' => $resumeVersion->id,
            'targeted_resume_id' => null,
            'company_name' => 'Acme Corp',
            'position' => 'Engineer',
            'date' => now()->toDateString(),
            'company_address' => '123 Main St',
            'greeting' => 'Dear Hiring Manager,',
            'message_body' => 'Thank you for your consideration.',
            'closing' => 'Sincerely,',
            'signature' => 'Jason Vertucio',
        ]);

        $service = app(CoverLetterDocumentService::class);
        $buildDocxData = new \ReflectionMethod($service, 'buildDocxData');
        $data = $buildDocxData->invoke($service, $coverLetter);

        $this->assertSame('jasonvertucio.com', $data['url']);
    }

    public function testNormalizeSplitPlaceholdersCollapsesSplitUrlToken(): void
    {
        $service = app(CoverLetterDocumentService::class);
        $normalize = new \ReflectionMethod($service, 'normalizeSplitPlaceholders');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:body><w:p>'
            . '<w:r><w:t>{</w:t></w:r>'
            . '<w:r><w:t>url</w:t></w:r>'
            . '<w:r><w:t>}</w:t></w:r>'
            . '</w:p></w:body></w:document>';

        $normalized = $normalize->invoke($service, $xml, ['url']);

        $this->assertStringContainsString('<w:t>{url}</w:t>', $normalized);
        $this->assertStringNotContainsString('<w:t>url</w:t>', $normalized);
    }
}
