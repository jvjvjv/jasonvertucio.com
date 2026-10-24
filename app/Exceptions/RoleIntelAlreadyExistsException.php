<?php

namespace App\Exceptions;

/**
 * Thrown when intel is started for an application that already has Role
 * intel — an application has at most one.
 */
class RoleIntelAlreadyExistsException extends ApplicationException
{
    public function __construct(string $message = 'This application already has intel.')
    {
        parent::__construct($message);
    }
}
