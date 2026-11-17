<?php

namespace App\Exceptions;

/**
 * Thrown when a follow-up is approved with a blank subject or body.
 */
class FollowUpIncompleteException extends ApplicationException
{
    public function __construct(string $message = 'A follow-up needs a subject and a body before it can be sent.')
    {
        parent::__construct($message);
    }
}
