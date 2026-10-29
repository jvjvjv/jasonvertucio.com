<?php

namespace App\Console\Commands;

use App\Models\FederatedIdentity;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Links an existing local user to a bspdx.com identity.
 *
 * Sign-in never attaches a bspdx.com identity to an existing account by email,
 * so this is the only way an account that predates federated sign-in —
 * including the owner's — keeps its roles once bspdx.com is the sole method.
 */
class LinkBspdxIdentityCommand extends Command
{
    protected $signature = 'user:link-bspdx
        {email : Email of the existing local user}
        {subject : The bspdx.com user id (UUID) to link}';

    protected $description = 'Link an existing user to a bspdx.com identity';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $subject = trim((string) $this->argument('subject'));

        if ($subject === '') {
            $this->error('The bspdx.com subject may not be empty.');

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();

        if ($user === null) {
            $this->error("User with email '{$email}' not found.");

            return self::FAILURE;
        }

        $provider = FederatedIdentity::PROVIDER_BSPDX;

        if ($user->federatedIdentities()->where('provider', $provider)->exists()) {
            $this->error("{$email} is already linked to a bspdx.com identity.");

            return self::FAILURE;
        }

        if (FederatedIdentity::where('provider', $provider)->where('subject', $subject)->exists()) {
            $this->error("bspdx.com identity {$subject} is already linked to another user.");

            return self::FAILURE;
        }

        $user->federatedIdentities()->create([
            'provider' => $provider,
            'subject' => $subject,
            'email_at_link' => $user->email,
        ]);

        $this->info("Linked {$email} to bspdx.com identity {$subject}.");

        return self::SUCCESS;
    }
}
