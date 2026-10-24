<?php

namespace App\Exceptions;

/**
 * Thrown when intel is started with no usable AI system: none was chosen and
 * there is no default, or the one chosen does not use the Company Intel
 * prompt.
 */
class NoIntelSystemException extends ApplicationException
{
    public function __construct(string $message = 'Choose an AI system that uses the Company Intel prompt.')
    {
        parent::__construct($message);
    }
}
