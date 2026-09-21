<?php

namespace Tests\Feature\Console;

use App\Models\CoverLetter;
use App\Models\ResumeVersion;
use App\Models\TargetedResume;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SweepExpiredDocumentsCommandTest extends TestCase
{
    use DatabaseTransactions;

    public function test_sweep_invalidates_only_expired_documents(): void
    {
        config(['resume.document_retention_hours' => 12]);

        $expiredPath = tempnam(sys_get_temp_dir(), 'docx-').'.docx';
        $freshPath = tempnam(sys_get_temp_dir(), 'docx-').'.docx';
        file_put_contents($expiredPath, 'expired');
        file_put_contents($freshPath, 'fresh');
        touch($expiredPath, now()->subHours(13)->timestamp);
        touch($freshPath, now()->subHours(1)->timestamp);

        $expiredResume = ResumeVersion::factory()->create(['docx_path' => $expiredPath]);
        $freshResume = ResumeVersion::factory()->create(['docx_path' => $freshPath]);

        $expiredTargetedPath = tempnam(sys_get_temp_dir(), 'pdf-').'.pdf';
        file_put_contents($expiredTargetedPath, 'expired');
        touch($expiredTargetedPath, now()->subHours(20)->timestamp);
        $expiredTargeted = TargetedResume::factory()->create(['pdf_path' => $expiredTargetedPath]);

        $freshCoverLetterPath = tempnam(sys_get_temp_dir(), 'docx-').'.docx';
        file_put_contents($freshCoverLetterPath, 'fresh');
        touch($freshCoverLetterPath, now()->subHours(2)->timestamp);
        $freshCoverLetter = CoverLetter::create([
            'company_name' => 'Acme',
            'position' => 'Engineer',
            'date' => now()->toDateString(),
            'greeting' => 'Dear Hiring Manager,',
            'message_body' => 'Body.',
            'docx_path' => $freshCoverLetterPath,
        ]);

        $this->artisan('resume:sweep-expired-documents')->assertSuccessful();

        $this->assertFileDoesNotExist($expiredPath);
        $this->assertNull($expiredResume->fresh()->docx_path);

        $this->assertFileExists($freshPath);
        $this->assertSame($freshPath, $freshResume->fresh()->docx_path);

        $this->assertFileDoesNotExist($expiredTargetedPath);
        $this->assertNull($expiredTargeted->fresh()->pdf_path);

        $this->assertFileExists($freshCoverLetterPath);
        $this->assertSame($freshCoverLetterPath, $freshCoverLetter->fresh()->docx_path);
    }
}
