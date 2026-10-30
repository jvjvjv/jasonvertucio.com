<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A bspdx.com sign-in that cannot complete.
 *
 * The message is the internal reason and goes to the log only; visitors see
 * {@see userMessage()}, which is generic for every failure except an email
 * that already belongs to an unlinked local account, where the visitor needs
 * to know to contact the site owner.
 */
class BspdxSignInException extends RuntimeException
{
    public const GENERIC_MESSAGE = 'Sign-in with bspdx.com did not complete. Please try again.';

    private function __construct(string $reason, private readonly string $userMessage)
    {
        parent::__construct($reason);
    }

    public static function failed(string $reason): self
    {
        return new self($reason, self::GENERIC_MESSAGE);
    }

    public static function emailBelongsToExistingUser(string $email): self
    {
        return new self(
            "Unlinked bspdx.com identity presented an email held by a local user ({$email}).",
            'An account with this email already exists here. Contact the site owner to have it connected to your bspdx.com account.',
        );
    }

    public function userMessage(): string
    {
        return $this->userMessage;
    }
}
