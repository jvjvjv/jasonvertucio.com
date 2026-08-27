<?php

namespace App\Services\ChatBot;

use App\Models\AiChatBot;
use App\Models\AiConversation;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The `ai/ChatBotsIndex` props, narrowed to the bots this viewer's roles allow.
 *
 * Host-owned reimplementation of the package's removed (0.11.0)
 * `Jvjvjv\CodeTalker\Services\ChatBot\ChatBotIndexPayload`, since the package
 * has no role concept — the host model does, via `allowed_roles`.
 */
class RoleFilteredChatBotIndexPayload
{
    public function __construct(private ChatBotRouteUrls $urls)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function build(mixed $user): array
    {
        $bots = AiChatBot::query()
            ->active()
            ->orderBy('name')
            ->get()
            ->filter(fn (AiChatBot $bot): bool => $this->canAccess($bot, $user))
            ->values();

        $conversationsByBotId = $this->conversationsFor($user, $bots);

        return $bots->map(fn (AiChatBot $bot): array => [
            'slug' => $bot->slug,
            'name' => $bot->name,
            'description' => $bot->description,
            'new_chat_url' => $this->urls->for($bot, 'new'),
            'status_url' => $this->urls->for($bot, 'status'),
            'conversations' => collect($conversationsByBotId->get($bot->id, []))
                ->map(fn (AiConversation $conversation): array => [
                    'title' => trim((string) ($conversation->title ?: 'New chat')),
                    'updated_at' => $conversation->last_message_at?->toIso8601String()
                        ?? $conversation->updated_at?->toIso8601String(),
                    'updated_at_human' => $conversation->last_message_at?->diffForHumans()
                        ?? $conversation->updated_at?->diffForHumans()
                        ?? 'just now',
                    'is_stale' => $conversation->is_stale,
                ])
                ->values()
                ->all(),
        ])->all();
    }

    /**
     * A bot with no `allowed_roles` is public; otherwise the viewer must hold one.
     */
    private function canAccess(AiChatBot $bot, ?User $user): bool
    {
        return empty($bot->allowed_roles) || $bot->allowsRole($user);
    }

    /**
     * @param  Collection<int, AiChatBot>  $bots
     * @return Collection<int, Collection<int, AiConversation>>
     */
    private function conversationsFor(mixed $user, Collection $bots): Collection
    {
        if ($user === null || $bots->isEmpty()) {
            return collect();
        }

        return AiConversation::query()
            ->where('user_id', $user->id)
            ->whereIn('ai_persona_id', $bots->pluck('id')->all())
            ->orderByLastMessageAtDesc()
            ->get()
            ->groupBy('ai_persona_id');
    }
}
