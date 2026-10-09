<?php

namespace App\Exceptions;

/**
 * Thrown when an application needs a resume version and neither the chosen
 * one nor a current one exists.
 */
class ResumeVersionUnavailableException extends ApplicationException
{
    public function __construct(string $message = 'A resume version is required to record an application.')
    {
        parent::__construct($message);
    }
}
