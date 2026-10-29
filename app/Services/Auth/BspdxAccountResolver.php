<?php

namespace App\Services\Auth;

use App\Exceptions\BspdxSignInException;
use App\Models\FederatedIdentity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Turns a bspdx.com identity into the local user it signs in as.
 *
 * An identity is matched on its stable subject. An unlinked identity is never
 * attached to an existing user by email — that would let whoever controls a
 * bspdx.com account with someone's address take over their account here — so
 * an email already held locally is refused, and existing accounts are linked
 * only by `user:link-bspdx`. Otherwise a new user is created with no roles.
 */
class BspdxAccountResolver
{
    public function resolve(BspdxIdentity $identity): User
    {
        $linked = FederatedIdentity::query()
            ->with('user')
            ->where('provider', FederatedIdentity::PROVIDER_BSPDX)
            ->where('subject', $identity->subject)
            ->first();

        if ($linked?->user !== null) {
            return $this->syncEmail($linked->user, $identity);
        }

        if (User::where('email', $identity->email)->exists()) {
            throw BspdxSignInException::emailBelongsToExistingUser($identity->email);
        }

        return DB::transaction(function () use ($identity): User {
            $user = User::create([
                'name' => $identity->displayName(),
                'email' => $identity->email,
                'password' => Hash::make(Str::random(64)),
            ]);

            $user->federatedIdentities()->create([
                'provider' => FederatedIdentity::PROVIDER_BSPDX,
                'subject' => $identity->subject,
                'email_at_link' => $identity->email,
            ]);

            return $user;
        });
    }

    /**
     * Follow an email change made at bspdx.com, unless the new address
     * already belongs to another local user.
     */
    private function syncEmail(User $user, BspdxIdentity $identity): User
    {
        if (strcasecmp($user->email, $identity->email) === 0) {
            return $user;
        }

        $taken = User::where('email', $identity->email)->whereKeyNot($user->getKey())->exists();

        if ($taken) {
            Log::warning('bspdx.com email change not applied: address belongs to another user', [
                'user_id' => $user->getKey(),
                'subject' => $identity->subject,
            ]);

            return $user;
        }

        $user->forceFill(['email' => $identity->email])->save();

        return $user;
    }
}
