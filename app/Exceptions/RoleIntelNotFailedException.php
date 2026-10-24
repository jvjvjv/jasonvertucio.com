<?php

namespace App\Exceptions;

/**
 * Thrown when a retry is asked for intel whose last run did not fail.
 */
class RoleIntelNotFailedException extends ApplicationException
{
    public function __construct(string $message = 'Only failed intel can be retried.')
    {
        parent::__construct($message);
    }
}
