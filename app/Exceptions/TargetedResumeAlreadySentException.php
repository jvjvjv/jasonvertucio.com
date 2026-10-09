<?php

namespace App\Exceptions;

/**
 * Thrown when a targeted resume is discarded after its application was
 * applied to: the document is then the record of what was sent.
 */
class TargetedResumeAlreadySentException extends ApplicationException
{
    public function __construct(string $message = 'This targeted resume was used to apply and can no longer be discarded.')
    {
        parent::__construct($message);
    }
}
