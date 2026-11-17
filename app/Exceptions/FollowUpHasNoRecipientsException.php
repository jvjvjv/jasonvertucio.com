<?php

namespace App\Exceptions;

/**
 * Thrown when a follow-up is approved on an application with no contacts to send it to.
 */
class FollowUpHasNoRecipientsException extends ApplicationException
{
    public function __construct(string $message = 'Add a contact before sending this follow-up.')
    {
        parent::__construct($message);
    }
}
