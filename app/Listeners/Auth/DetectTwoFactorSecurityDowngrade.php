<?php

namespace App\Listeners\Auth;

use App\Enums\SecurityMethodKind;
use App\Events\UserSecurityMethodRemoved;
use Illuminate\Contracts\Queue\ShouldQueue;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;

class DetectTwoFactorSecurityDowngrade implements ShouldQueue
{
    public function handle(TwoFactorAuthenticationDisabled $event): void
    {
        $user = $event->user;

        // The disable action fires even for an unconfirmed setup, and by the
        // time this event dispatches the model has already been saved with
        // two_factor_confirmed_at nulled. save() re-syncs getOriginal() to
        // the post-save value, so the pre-save value survives only in
        // getPrevious() (captured by syncChanges() before syncOriginal()
        // runs) — that's what "was it actually active" must be checked against.
        if (! $user->wasChanged('two_factor_confirmed_at')
            || is_null($user->getPrevious()['two_factor_confirmed_at'] ?? null)) {
            return;
        }

        UserSecurityMethodRemoved::dispatch(
            $user,
            SecurityMethodKind::TwoFactor,
            0,
            now()->toImmutable(),
        );
    }
}
