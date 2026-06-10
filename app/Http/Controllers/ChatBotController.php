<?php

namespace App\Http\Controllers;

use App\Models\AiChatBot;
use App\Models\User;
use Jvjvjv\CodeTalker\Models\AiConversation;
use Jvjvjv\CodeTalker\Services\AiChatBotConversationService;
use Jvjvjv\CodeTalker\Services\AiModelReadinessService;
use Jvjvjv\CodeTalker\Http\Controllers\ChatBotController as PackageChatBotController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ChatBotController extends PackageChatBotController
{
    private AiModelReadinessService $readinessService;

    public function __construct(
        AiChatBotConversationService $conversationService,
        AiModelReadinessService $readinessService,
    ) {
        parent::__construct($conversationService, $readinessService);
        $this->readinessService = $readinessService;
    }

    public function index(Request $request): InertiaResponse
    {
        $user = $request->user();

        $bots = AiChatBot::query()
            ->active()
            ->orderBy('name')
            ->get()
            ->filter(fn (AiChatBot $bot): bool => $this->canAccess($bot, $user))
            ->values();

        $conversationsByBotId = collect();

        if ($user !== null && $bots->isNotEmpty()) {
            $conversationsByBotId = AiConversation::query()
                ->where('user_id', $user->id)
                ->whereIn('ai_chat_bot_id', $bots->pluck('id')->all())
                ->orderByLastMessageAtDesc()
                ->get()
                ->groupBy('ai_chat_bot_id');
        }

        return Inertia::render('ai/ChatBotsIndex', [
            'bots' => $bots->map(function (AiChatBot $bot) use ($conversationsByBotId): array {
                $prefix = $bot->usesRootAccessPath() ? 'chat-bots.root.' : 'chat-bots.chat.';
                $conversations = collect($conversationsByBotId->get($bot->id, []));

                return [
                    'slug' => $bot->slug,
                    'name' => $bot->name,
                    'description' => $bot->description,
                    'new_chat_url' => route($prefix . 'new', $bot),
                    'status_url' => route($prefix . 'status', $bot),
                    'conversations' => $conversations->map(function (AiConversation $conversation): array {
                        return [
                            'title' => trim((string) ($conversation->title ?: 'New chat')),
                            'updated_at' => $conversation->last_message_at?->toIso8601String()
                                ?? $conversation->updated_at?->toIso8601String(),
                            'updated_at_human' => $conversation->last_message_at?->diffForHumans()
                                ?? $conversation->updated_at?->diffForHumans()
                                ?? 'just now',
                            'is_stale' => $conversation->is_stale,
                        ];
                    })->values()->all(),
                ];
            })->all(),
        ]);
    }

    public function statuses(Request $request): JsonResponse
    {
        $user = $request->user();

        $bots = AiChatBot::query()
            ->active()
            ->with('aiSystem')
            ->orderBy('name')
            ->get()
            ->filter(fn (AiChatBot $bot): bool => $this->canAccess($bot, $user))
            ->values();

        $statusesBySystemId = [];
        $statusesByBotSlug = [];

        foreach ($bots as $bot) {
            if (! array_key_exists($bot->ai_system_id, $statusesBySystemId)) {
                $statusesBySystemId[$bot->ai_system_id] = $this->readinessService->statusForSystem($bot->aiSystem);
            }

            $statusesByBotSlug[$bot->slug] = $statusesBySystemId[$bot->ai_system_id];
        }

        return response()->json(['statuses' => $statusesByBotSlug]);
    }

    private function canAccess(AiChatBot $bot, ?User $user): bool
    {
        return empty($bot->allowed_roles) || $bot->allowsRole($user);
    }
}
