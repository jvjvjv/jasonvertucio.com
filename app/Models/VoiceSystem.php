<?php

namespace App\Models;

use App\Models\Concerns\IsAiSystemSubset;
use Illuminate\Database\Eloquent\Builder;
use Jvjvjv\CodeTalker\Enums\AiSystemRole;
use Jvjvjv\CodeTalker\Models\AiSystem;
use LogicException;

/**
 * An AiSystem that turns speech into text (`transcription`) or text into
 * speech (`audio`). The Voice Systems pages bind and list this, so a chat
 * system's id is simply not found there.
 */
class VoiceSystem extends AiSystem
{
    use IsAiSystemSubset;

    public const array ROLES = [AiSystemRole::Audio, AiSystemRole::Transcription];

    protected static function booted(): void
    {
        static::addGlobalScope('role', fn (Builder $query) => $query->whereIn(
            'role',
            array_map(static fn (AiSystemRole $role): string => $role->value, self::ROLES),
        ));

        static::creating(function (VoiceSystem $system): void {
            if (! in_array($system->role, self::ROLES, true)) {
                throw new LogicException('A voice system needs the audio or transcription role.');
            }
        });
    }
}
