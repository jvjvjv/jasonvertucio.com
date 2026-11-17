<?php

namespace App\Enums;

/**
 * Why a follow-up draft was discarded: by hand, because a new status entry
 * made it stale, or because follow-ups were switched off.
 */
enum FollowUpDiscardReason: string
{
    case Manual = 'manual';
    case Superseded = 'superseded';
    case Disabled = 'disabled';
}
