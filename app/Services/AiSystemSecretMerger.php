<?php

namespace App\Services;

/**
 * Applies the "keep unless supplied" contract to an AiSystem's secrets.
 *
 * Secrets are never sent to the browser, so a form cannot echo them back. An
 * absent or empty value therefore means "keep what is stored", a value means
 * "replace", and removal is a separate, explicit flag.
 */
class AiSystemSecretMerger
{
    /**
     * The provider key to persist.
     */
    public function apiKey(string $stored, mixed $submitted, bool $clear): string
    {
        if ($clear) {
            return '';
        }

        return is_string($submitted) && $submitted !== '' ? $submitted : $stored;
    }

    /**
     * The service credentials to persist.
     *
     * @param  array<string, mixed>|null  $stored
     * @param  array<string, mixed>|string|null  $submitted  Decoded or JSON text
     * @return array<string, mixed>|null
     */
    public function credentials(?array $stored, array|string|null $submitted, bool $clear): ?array
    {
        if ($clear) {
            return null;
        }

        $decoded = is_string($submitted) ? json_decode($submitted, true) : $submitted;

        return is_array($decoded) && $decoded !== [] ? $decoded : $stored;
    }

    /**
     * The web-tool policy to persist.
     *
     * Submitted header values replace a domain's stored headers wholesale; a
     * domain submitted without headers keeps what it has; `cleared_hosts`
     * removes a domain's headers; a domain no longer in `allowed_domains`
     * loses them.
     *
     * @param  array<string, mixed>|null  $stored
     * @param  array<string, mixed>|null  $submitted
     * @return array<string, mixed>|null
     */
    public function webToolPolicy(?array $stored, ?array $submitted): ?array
    {
        if ($submitted === null || $submitted === []) {
            return null;
        }

        $domains = array_values(array_filter($submitted['allowed_domains'] ?? [], 'is_string'));
        $storedCredentials = is_array($stored['credentials'] ?? null) ? $stored['credentials'] : [];
        $submittedCredentials = is_array($submitted['credentials'] ?? null) ? $submitted['credentials'] : [];
        $cleared = array_filter($submitted['cleared_hosts'] ?? [], 'is_string');

        $credentials = [];
        foreach ($domains as $domain) {
            $headers = $submittedCredentials[$domain] ?? null;

            if (is_array($headers) && $headers !== []) {
                $credentials[$domain] = $headers;
            } elseif (! in_array($domain, $cleared, true) && is_array($storedCredentials[$domain] ?? null) && $storedCredentials[$domain] !== []) {
                $credentials[$domain] = $storedCredentials[$domain];
            }
        }

        unset($submitted['cleared_hosts'], $submitted['credentials'], $submitted['has_headers']);
        $submitted['allowed_domains'] = $domains;

        if ($credentials !== []) {
            $submitted['credentials'] = $credentials;
        }

        return $submitted;
    }
}
