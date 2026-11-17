<?php

namespace App\Exceptions;

/**
 * Thrown when a follow-up is edited, approved or discarded in a state that
 * does not allow it: only a pending draft can be edited or discarded, and
 * only a pending or failed one approved. A second approve of one already
 * sending or sent lands here, which keeps a follow-up from going out twice.
 */
class FollowUpNotPendingException extends ApplicationException
{
    public function __construct(string $message = 'This follow-up is no longer waiting for review.')
    {
        parent::__construct($message);
    }
}
