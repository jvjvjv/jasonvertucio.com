<?php

namespace App\Exceptions;

/**
 * Thrown when an application that has already been applied to is passed on.
 * Its status is owned by its status history from then on.
 */
class ApplicationInPipelineException extends ApplicationException
{
    public function __construct(string $message = 'An application that has been applied to cannot be marked as passed.')
    {
        parent::__construct($message);
    }
}
