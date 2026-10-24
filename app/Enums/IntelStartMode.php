<?php

namespace App\Enums;

/**
 * How an intel turn runs: in the queue, with nobody watching, or streamed to
 * the intel page when it opens.
 */
enum IntelStartMode: string
{
    case Background = 'background';
    case Stream = 'stream';
}
