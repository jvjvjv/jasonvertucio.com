<?php

namespace App\Enums;

enum RoleIntelStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Ready = 'ready';
    case Failed = 'failed';

    /**
     * Whether a turn is waiting to run or running, so the page should keep
     * checking for results.
     */
    public function isInProgress(): bool
    {
        return $this === self::Queued || $this === self::Running;
    }
}
