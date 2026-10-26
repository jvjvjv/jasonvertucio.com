<?php

namespace App\Exceptions;

/**
 * Thrown when a session is switched to an AI system its feature does not
 * allow, or to the system it already runs on.
 */
class SystemNotEligibleException extends ApplicationException
{
    public function __construct(string $message = 'That AI system cannot run this session.')
    {
        parent::__construct($message);
    }
}
