<?php

namespace App\Models;

use App\Models\Concerns\IsAiSystemSubset;
use Illuminate\Database\Eloquent\Builder;
use Jvjvjv\CodeTalker\Enums\AiSystemRole;
use Jvjvjv\CodeTalker\Models\AiSystem;

/**
 * An AiSystem that holds conversations. Query this, not AiSystem, wherever a
 * chat model is chosen, so a voice system can never be offered as one.
 *
 * Only this app's own queries return it: code-talker's services still hand back
 * plain AiSystem instances, and keep their own role guards.
 */
class ChatSystem extends AiSystem
{
    use IsAiSystemSubset;

    protected static function booted(): void
    {
        static::addGlobalScope('role', fn (Builder $query) => $query->where('role', AiSystemRole::Chat->value));

        static::creating(function (ChatSystem $system): void {
            $system->role = AiSystemRole::Chat;
        });
    }
}
