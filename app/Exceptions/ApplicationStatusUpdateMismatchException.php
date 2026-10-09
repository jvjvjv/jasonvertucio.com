<?php

namespace App\Exceptions;

/**
 * Thrown when a status-history entry is edited or deleted through an
 * application it does not belong to. Callers treat it as not found.
 */
class ApplicationStatusUpdateMismatchException extends ApplicationException
{
    public function __construct(string $message = 'Status update not found for this application.')
    {
        parent::__construct($message);
    }
}
