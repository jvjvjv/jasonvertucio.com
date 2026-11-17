<?php

namespace App\Exceptions;

/**
 * Thrown when a follow-up is edited, approved or discarded through an
 * application it does not belong to. Callers treat it as not found.
 */
class ApplicationFollowUpMismatchException extends ApplicationException
{
    public function __construct(string $message = 'Follow-up not found for this application.')
    {
        parent::__construct($message);
    }
}
