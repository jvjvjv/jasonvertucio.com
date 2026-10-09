<?php

namespace App\Support;

use Jvjvjv\CodeTalker\Models\AiSystem;

/**
 * An application's AI session writes both the targeted resume and the cover
 * letter with one AI system, so a session cannot be started while the two
 * features default to different systems.
 */
class AnalysisSystemGuard
{
    public const string SEPARATE_MODELS_MESSAGE = 'Separate models for Targeted Resume and Cover Letter are unsupported at this time.';

    /**
     * The reason a session cannot be started right now, or null when it can.
     */
    public function refusal(): ?string
    {
        $resumeDefault = AiSystem::defaultForFeature('targeted-resume');
        $coverLetterDefault = AiSystem::defaultForFeature('cover-letter');

        if ($resumeDefault && $coverLetterDefault && $resumeDefault->id !== $coverLetterDefault->id) {
            return self::SEPARATE_MODELS_MESSAGE;
        }

        return null;
    }
}
