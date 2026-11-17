<?php

namespace App\Exceptions;

/**
 * Thrown when a contact is edited or removed through an application it does
 * not belong to. Callers treat it as not found.
 */
class ApplicationContactMismatchException extends ApplicationException
{
    public function __construct(string $message = 'Contact not found for this application.')
    {
        parent::__construct($message);
    }
}
