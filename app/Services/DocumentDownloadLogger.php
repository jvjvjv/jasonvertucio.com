<?php

namespace App\Services;

use App\Models\CoverLetter;
use App\Models\DocumentDownload;
use App\Models\ResumeVersion;
use App\Models\TargetedResume;

class DocumentDownloadLogger
{
    public function log(
        ResumeVersion|TargetedResume|CoverLetter $document,
        string $type,
        bool $servedCachedDocument,
        string $ipAddress,
    ): DocumentDownload {
        return DocumentDownload::create([
            'resume_id' => $document instanceof ResumeVersion ? $document->id : null,
            'targeted_resume_id' => $document instanceof TargetedResume ? $document->id : null,
            'cover_letter_id' => $document instanceof CoverLetter ? $document->id : null,
            'type' => $type,
            'served_cached_document' => $servedCachedDocument,
            'ip_address' => $ipAddress,
        ]);
    }
}
