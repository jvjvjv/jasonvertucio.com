<?php

namespace App\Exceptions;

/**
 * Thrown when a status-history entry is given a status outside the pipeline
 * (draft or passed).
 */
class NonPipelineStatusException extends ApplicationException
{
    public function __construct(string $message = 'Status history entries must use a pipeline status.')
    {
        parent::__construct($message);
    }
}
