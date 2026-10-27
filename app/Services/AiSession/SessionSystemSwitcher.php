<?php

namespace App\Services\AiSession;

use App\Contracts\SessionSystemEligibility;
use App\Exceptions\SystemNotEligibleException;
use App\Support\SessionNotes;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Jvjvjv\CodeTalker\Models\AiConversation;
use Jvjvjv\CodeTalker\Models\AiConversationMessage;
use Jvjvjv\CodeTalker\Models\AiSystem;

/**
 * Moves a session to another AI system for good. The saved prompt and the
 * transcript are left as they are; only the system that receives them on
 * the next turn changes, and a display-only note records the move.
 *
 * Whether a turn is running is the caller's to check, since each feature
 * knows that differently.
 */
class SessionSystemSwitcher
{
    /**
     * Long enough to outlast any turn, short enough that a lock orphaned by
     * a killed process does not block switching for long.
     */
    public const int TURN_LOCK_SECONDS = 600;

    public const string CONTEXT_CONFIGURED = 'configured';

    public const string CONTEXT_MODEL_MAX = 'model_max';

    public const string CONTEXT_UNKNOWN = 'unknown';

    /**
     * The lock a streaming turn holds, and a switch takes, so a session's
     * system never changes under a turn that is still running.
     */
    public static function turnLock(int $conversationId): Lock
    {
        return Cache::lock("ai-conversation-turn:{$conversationId}", self::TURN_LOCK_SECONDS);
    }

    /**
     * What the switch dialog shows: the current system, the systems the
     * session may move to, and how much the next turn will send.
     *
     * @return array{
     *     current: array{id: int, name: string, model: string}|null,
     *     systems: array<int, array{id: int, name: string, model: string, context: array{tokens: int|null, source: string}}>,
     *     estimated_tokens: int
     * }
     */
    public function options(AiConversation $conversation, SessionSystemEligibility $eligibility): array
    {
        $current = $conversation->aiSystem;

        return [
            'current' => $current === null ? null : $this->summary($current),
            'systems' => $this->candidates($conversation, $eligibility)
                ->map(fn (AiSystem $system): array => [...$this->summary($system), 'context' => $this->context($system)])
                ->all(),
            'estimated_tokens' => $this->estimatedTokens($conversation),
        ];
    }

    /**
     * Whether the session has anywhere else to go.
     */
    public function canSwitch(AiConversation $conversation, SessionSystemEligibility $eligibility): bool
    {
        return $this->candidates($conversation, $eligibility)->isNotEmpty();
    }

    /**
     * Repoint the session at the system and record the move in its
     * transcript, in one transaction.
     */
    public function switch(AiConversation $conversation, AiSystem $system, SessionSystemEligibility $eligibility): void
    {
        if ($conversation->ai_system_id === $system->id) {
            throw new SystemNotEligibleException('This session already runs on that AI system.');
        }

        if (! $eligibility->isEligible($system)) {
            throw new SystemNotEligibleException;
        }

        $previous = $conversation->aiSystem;

        DB::transaction(function () use ($conversation, $system, $previous): void {
            $conversation->update(['ai_system_id' => $system->id]);

            AiConversationMessage::create([
                'ai_conversation_id' => $conversation->id,
                'role' => 'user',
                'content' => sprintf('Switched AI system from %s to %s.', $previous->name ?? 'a removed system', $system->name),
                'metadata' => [
                    'origin' => SessionNotes::ORIGIN_SYSTEM_SWITCHED,
                    'from_ai_system_id' => $previous?->id,
                    'from_name' => $previous?->name,
                    'to_ai_system_id' => $system->id,
                    'to_name' => $system->name,
                ],
            ]);
        });

        $conversation->unsetRelation('aiSystem');
    }

    /**
     * About how many tokens the next turn sends: the saved instructions and
     * the transcript it replays, at roughly four characters a token.
     */
    public function estimatedTokens(AiConversation $conversation): int
    {
        $characters = SessionNotes::replayable($conversation->messages()->get())
            ->sum(fn (AiConversationMessage $message): int => mb_strlen((string) $message->content));

        return (int) ceil($characters / 4);
    }

    /**
     * The best-known context limit: the system's own setting, else the
     * maximum its model reports, else nothing.
     *
     * @return array{tokens: int|null, source: string}
     */
    public function context(AiSystem $system): array
    {
        if ($system->context_length !== null) {
            return ['tokens' => $system->context_length, 'source' => self::CONTEXT_CONFIGURED];
        }

        $maximum = $system->model_capabilities['max_context_length'] ?? null;

        if (is_numeric($maximum)) {
            return ['tokens' => (int) $maximum, 'source' => self::CONTEXT_MODEL_MAX];
        }

        return ['tokens' => null, 'source' => self::CONTEXT_UNKNOWN];
    }

    /**
     * @return Collection<int, AiSystem>
     */
    private function candidates(AiConversation $conversation, SessionSystemEligibility $eligibility): Collection
    {
        return $eligibility->selectable()
            ->toBase()
            ->reject(fn (AiSystem $system): bool => $system->id === $conversation->ai_system_id)
            ->values();
    }

    /**
     * @return array{id: int, name: string, model: string}
     */
    private function summary(AiSystem $system): array
    {
        return ['id' => $system->id, 'name' => $system->name, 'model' => $system->model];
    }
}
