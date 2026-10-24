<?php

namespace App\Enums;

enum SecurityMethodKind: string
{
    case TwoFactor = 'two_factor';
    case Passkey = 'passkey';

    public function label(): string
    {
        return match ($this) {
            self::TwoFactor => 'two-factor authentication',
            self::Passkey => 'passkey authentication',
        };
    }
}
