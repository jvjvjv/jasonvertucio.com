<?php

namespace App\Exceptions;

/**
 * Thrown when a company is marked wrong on intel that has none attached.
 */
class RoleIntelHasNoCompanyException extends ApplicationException
{
    public function __construct(string $message = 'No company is attached to this intel.')
    {
        parent::__construct($message);
    }
}
