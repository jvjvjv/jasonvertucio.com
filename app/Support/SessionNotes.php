<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Jvjvjv\CodeTalker\Models\AiConversationMessage;

/**
 * Notes written into a session's transcript for the reader only. A
 * display-only note is shown in the chat but never replayed to the model,
 * unlike the notes (`manual_edit`, `resume_discarded`) that tell the agent
 * about something that happened outside of chat.
 */
class SessionNotes
{
    public const string ORIGIN_SYSTEM_SWITCHED = 'system_switched';

    public const array DISPLAY_ONLY_ORIGINS = [self::ORIGIN_SYSTEM_SWITCHED];

    public static function isDisplayOnly(AiConversationMessage $message): bool
    {
        return in_array($message->metadata['origin'] ?? null, self::DISPLAY_ONLY_ORIGINS, true);
    }

    /**
     * The messages a turn replays: everything but display-only notes.
     *
     * @template TKey of array-key
     *
     * @param  Collection<TKey, AiConversationMessage>  $messages
     * @return Collection<int, AiConversationMessage>
     */
    public static function replayable(Collection $messages): Collection
    {
        return $messages->reject(fn (AiConversationMessage $message): bool => self::isDisplayOnly($message))->values();
    }
}
