<?php

namespace App\Exceptions;

/**
 * Thrown when an analysis is begun on an application that already has an AI
 * session — an application has at most one.
 */
class ApplicationAlreadyHasConversationException extends ApplicationException
{
    public function __construct(string $message = 'This application already has an AI session.')
    {
        parent::__construct($message);
    }
}
