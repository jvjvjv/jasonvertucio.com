<?php

namespace App\Exceptions;

/**
 * Thrown when a session's AI system is switched while one of its turns is
 * still running.
 */
class SessionTurnInProgressException extends ApplicationException
{
    public function __construct(string $message = 'A reply is still being written. Wait for it to finish, then switch.')
    {
        parent::__construct($message);
    }
}
