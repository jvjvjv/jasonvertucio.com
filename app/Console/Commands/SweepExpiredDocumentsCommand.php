<?php

namespace App\Console\Commands;

use App\Models\CoverLetter;
use App\Models\ResumeVersion;
use App\Models\TargetedResume;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reclaims generated DOCX/PDF files whose retention window has passed.
 *
 * In `cache` mode a file is otherwise only invalidated lazily, the next time
 * something checks it (e.g. a download request); this sweep reclaims files
 * that nobody downloads again. Harmless in `delete_after_serve` mode, where
 * there should be nothing left for it to find.
 */
class SweepExpiredDocumentsCommand extends Command
{
    protected $signature = 'resume:sweep-expired-documents';

    protected $description = 'Invalidate generated resume/cover-letter documents past the retention window';

    public function handle(): int
    {
        $retentionHours = (int) config('resume.document_retention_hours');
        $cutoff = now()->subHours($retentionHours)->timestamp;

        $invalidated = 0;
        $invalidated += $this->sweep(ResumeVersion::query(), $cutoff);
        $invalidated += $this->sweep(TargetedResume::query(), $cutoff);
        $invalidated += $this->sweep(CoverLetter::query(), $cutoff);

        $this->info("Invalidated {$invalidated} expired document(s).");

        return self::SUCCESS;
    }

    /**
     * @param  Builder<ResumeVersion|TargetedResume|CoverLetter>  $query
     */
    private function sweep(Builder $query, int $cutoff): int
    {
        $invalidated = 0;

        $query->where(function (Builder $query) {
            $query->whereNotNull('docx_path')->orWhereNotNull('pdf_path');
        })->chunkById(100, function ($documents) use ($cutoff, &$invalidated) {
            foreach ($documents as $document) {
                if ($document->docx_path !== null && $this->isExpired($document->docx_path, $cutoff)) {
                    $document->invalidateDocx();
                    $invalidated++;
                }

                if ($document->pdf_path !== null && $this->isExpired($document->pdf_path, $cutoff)) {
                    $document->invalidatePdf();
                    $invalidated++;
                }
            }
        });

        return $invalidated;
    }

    private function isExpired(string $path, int $cutoff): bool
    {
        return ! file_exists($path) || filemtime($path) < $cutoff;
    }
}
