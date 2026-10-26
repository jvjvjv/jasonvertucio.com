<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Jvjvjv\CodeTalker\Models\AiSystem;

/**
 * Which AI systems a feature's sessions may run on, so a session can only be
 * started on, or switched to, a system whose prompt fits the feature.
 */
interface SessionSystemEligibility
{
    /**
     * Every eligible system, ordered by name.
     *
     * @return Collection<int, AiSystem>
     */
    public function selectable(): Collection;

    public function isEligible(AiSystem $system): bool;
}
