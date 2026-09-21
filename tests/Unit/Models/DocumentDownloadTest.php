<?php

namespace Tests\Unit\Models;

use App\Models\CoverLetter;
use App\Models\DocumentDownload;
use App\Models\ResumeVersion;
use App\Models\TargetedResume;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DocumentDownloadTest extends TestCase
{
    use DatabaseTransactions;

    public function test_logs_a_main_resume_download(): void
    {
        $download = DocumentDownload::factory()->docx()->create();

        $this->assertInstanceOf(ResumeVersion::class, $download->resume);
        $this->assertNull($download->targeted_resume_id);
        $this->assertNull($download->cover_letter_id);
        $this->assertSame('docx', $download->type);
    }

    public function test_logs_a_targeted_resume_download(): void
    {
        $download = DocumentDownload::factory()->forTargetedResume()->pdf()->create();

        $this->assertInstanceOf(TargetedResume::class, $download->targetedResume);
        $this->assertNull($download->resume_id);
        $this->assertNull($download->cover_letter_id);
    }

    public function test_logs_a_cover_letter_download(): void
    {
        $download = DocumentDownload::factory()->forCoverLetter()->cached()->create();

        $this->assertInstanceOf(CoverLetter::class, $download->coverLetter);
        $this->assertNull($download->resume_id);
        $this->assertNull($download->targeted_resume_id);
        $this->assertTrue($download->served_cached_document);
    }

    public function test_served_cached_document_casts_to_boolean(): void
    {
        $download = DocumentDownload::factory()->freshlyGenerated()->create();

        $this->assertFalse($download->served_cached_document);
        $this->assertIsBool($download->fresh()->served_cached_document);
    }

    public function test_document_accessor_resolves_the_downloaded_resume(): void
    {
        $download = DocumentDownload::factory()->docx()->create();

        $this->assertTrue($download->resume->is($download->document));
    }

    public function test_document_accessor_resolves_the_downloaded_targeted_resume(): void
    {
        $download = DocumentDownload::factory()->forTargetedResume()->pdf()->create();

        $this->assertTrue($download->targetedResume->is($download->document));
    }

    public function test_document_accessor_resolves_the_downloaded_cover_letter(): void
    {
        $download = DocumentDownload::factory()->forCoverLetter()->cached()->create();

        $this->assertTrue($download->coverLetter->is($download->document));
    }

    public function test_document_accessor_is_null_when_nothing_set(): void
    {
        $download = DocumentDownload::factory()->docx()->create(['resume_id' => null]);

        $this->assertNull($download->document);
    }
}
