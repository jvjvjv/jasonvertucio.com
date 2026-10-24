<?php

namespace App\Exceptions;

/**
 * Thrown when a discard is requested for an application that has no
 * targeted resume.
 */
class TargetedResumeMissingException extends ApplicationException
{
    public function __construct(string $message = 'This application has no targeted resume to discard.')
    {
        parent::__construct($message);
    }
}
