<?php

namespace App\Services\Concerns;

/**
 * Whether a generated document's file is still considered current, given
 * `config('resume.document_retention_mode')`. Shared by every service that
 * implements generate-if-missing (`ensureDocx()`/`ensurePdf()`).
 */
trait ChecksDocumentRetention
{
    protected function isCurrentlyValid(?string $path): bool
    {
        if ($path === null || ! file_exists($path)) {
            return false;
        }

        if (config('resume.document_retention_mode') !== 'cache') {
            return true;
        }

        $retentionHours = (int) config('resume.document_retention_hours');

        return filemtime($path) >= now()->subHours($retentionHours)->timestamp;
    }
}
