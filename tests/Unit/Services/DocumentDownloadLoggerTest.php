<?php

namespace Tests\Unit\Services;

use App\Models\CoverLetter;
use App\Models\ResumeVersion;
use App\Models\TargetedResume;
use App\Services\DocumentDownloadLogger;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DocumentDownloadLoggerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_logs_a_resume_download(): void
    {
        $resumeVersion = ResumeVersion::factory()->create();

        $download = (new DocumentDownloadLogger)->log($resumeVersion, 'docx', true, '203.0.113.5');

        $this->assertSame($resumeVersion->id, $download->resume_id);
        $this->assertNull($download->targeted_resume_id);
        $this->assertNull($download->cover_letter_id);
        $this->assertSame('docx', $download->type);
        $this->assertTrue($download->served_cached_document);
        $this->assertSame('203.0.113.5', $download->ip_address);
    }

    public function test_logs_a_targeted_resume_download(): void
    {
        $targetedResume = TargetedResume::factory()->create();

        $download = (new DocumentDownloadLogger)->log($targetedResume, 'pdf', false, '203.0.113.6');

        $this->assertNull($download->resume_id);
        $this->assertSame($targetedResume->id, $download->targeted_resume_id);
        $this->assertNull($download->cover_letter_id);
    }

    public function test_logs_a_cover_letter_download(): void
    {
        $coverLetter = CoverLetter::create([
            'company_name' => 'Acme',
            'position' => 'Engineer',
            'date' => now()->toDateString(),
            'greeting' => 'Dear Hiring Manager,',
            'message_body' => 'Body.',
        ]);

        $download = (new DocumentDownloadLogger)->log($coverLetter, 'docx', false, '203.0.113.7');

        $this->assertNull($download->resume_id);
        $this->assertNull($download->targeted_resume_id);
        $this->assertSame($coverLetter->id, $download->cover_letter_id);
    }
}
