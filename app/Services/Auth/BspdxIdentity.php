<?php

namespace App\Services\Auth;

/**
 * The identity bspdx.com's userinfo endpoint reported for a signed-in person.
 */
final readonly class BspdxIdentity
{
    public function __construct(
        public string $subject,
        public string $email,
        public ?string $name,
    ) {}

    /**
     * The name to give a new local account. bspdx.com currently reports the
     * email as the name, which reads poorly on comments, so the email's local
     * part is used when no distinct name is available.
     */
    public function displayName(): string
    {
        $name = trim((string) $this->name);

        if ($name !== '' && strcasecmp($name, $this->email) !== 0) {
            return mb_substr($name, 0, 191);
        }

        return mb_substr(strstr($this->email, '@', true) ?: $this->email, 0, 191);
    }
}
