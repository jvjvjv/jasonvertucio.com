<?php

namespace App\Exceptions;

/**
 * Thrown when a status-history entry is added to an application whose status
 * is terminal (accepted, hired or rejected).
 */
class TerminalApplicationException extends ApplicationException
{
    public function __construct(string $message = 'Cannot add a status update to a terminal application.')
    {
        parent::__construct($message);
    }
}
