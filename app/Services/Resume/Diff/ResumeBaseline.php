<?php

namespace App\Services\Resume\Diff;

/**
 * What a draft revision is being compared with on the review page.
 */
final readonly class ResumeBaseline
{
    public const string BASE = 'base';

    public const string LIVE = 'live';

    public const string PREVIOUS = 'previous';

    /**
     * @param  string  $label  the baseline as a sentence fragment ("version 2026.1.3, the one it branched from")
     * @param  array<mixed>  $data  the baseline's editable data
     * @param  array<string, string>  $available  every mode on offer => its option label
     * @param  bool  $fellBack  a requested mode was unknown or unavailable, so base is used
     */
    public function __construct(
        public string $mode,
        public string $label,
        public array $data,
        public array $available,
        public bool $fellBack = false,
    ) {}
}
