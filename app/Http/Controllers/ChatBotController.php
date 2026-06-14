<?php

namespace App\Http\Controllers;

use App\Models\AiChatBot;
use App\Models\AiConversation;
use App\Models\User;
use Jvjvjv\CodeTalker\Models\AiChatBot as BaseAiChatBot;
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

    public function show(Request $request, BaseAiChatBot $aiChatBot): InertiaResponse {

        $this->abortIfInaccessible($request, $aiChatBot);

        $conversation = $this->storedConversation($request, $aiChatBot);
        $history = $this->historyForBot($request, $aiChatBot);
        $messages = [];

        if ($conversation !== null) {
            $messages = $conversation->messages()
                ->where('role', '!=', 'system')
                ->orderBy('created_at')
                ->get()
                ->map(fn($message) => [
                    'role' => $message->role,
                    'content' => $message->content,
                    'reasoning_content' => $message->reasoning_content,
                    'blocks' => $message->blocks,
                ])
                ->all();
        }

        // Compute the hash-based URL for the current conversation (if it has one).
        $chatHash = $conversation?->chat_hash;
        $chatUrl = $chatHash
            ? '/chat/' . $aiChatBot->slug . '/' . $chatHash
            : null;

        Log::info('ChatBotController@show', [
            'conversation_id' => $conversation?->id,
            'bot' => $aiChatBot,
        ]);

        return Inertia::render('ai/ChatBot', [
            'bot' => [
                'name' => $aiChatBot->name,
                'description' => $aiChatBot->description,
                'require_visitor_identity' => $aiChatBot->require_visitor_identity,
                'allowed_roles' => $aiChatBot->allowed_roles ?? [],
                'total_cost_usd' => (float) (AiConversation::query()
                    ->where('ai_chat_bot_id', $aiChatBot->id)
                    ->sum('usage_cost_usd') ?? 0),
            ],
            'messages' => $messages,
            'history' => $history,
            'messageUrl' => $this->routeUrlFor($aiChatBot, 'message'),
            'resetUrl' => $this->routeUrlFor($aiChatBot, 'reset'),
            'switchUrl' => $this->routeUrlFor($aiChatBot, 'switch'),
            'statusUrl' => $this->routeUrlFor($aiChatBot, 'status'),
            'warmupUrl' => $this->routeUrlFor($aiChatBot, 'warmup'),
            'chatUrl' => $chatUrl,
            'chatUrlBase' => '/chat/' . $aiChatBot->slug . '/',
            'showIdentityForm' => !$request->user()
                && $aiChatBot->require_visitor_identity
                && $conversation === null,
        ]);
    }

    /**
     * Load a conversation by its hash or UUID (UUID is the fallback for direct linking).
     * This allows accessing a specific chat from any computer.
     */
    public function showByHash(Request $request, string $slug, string $hash): InertiaResponse {
        $conversation = AiConversation::findByChatHashOrUuid($hash);

        if ($conversation === null) {
            abort(404);
        }

        $bot = $conversation->aiChatBot;

        $this->abortIfInaccessible($request, $bot);

        // Restore the conversation as the current one in session
        $state = $this->storedState($request, $bot);
        $state['current'] = $conversation->public_id;
        $history = collect($state['history'] ?? []);
        if (!$history->contains(fn(array $item) => $item['public_id'] === $conversation->public_id)) {
            $history->prepend([
                'handle' => (string) Str::ulid(),
                'public_id' => $conversation->public_id,
            ]);
        }
        $this->putStoredState($request, $bot, [
            'current' => $conversation->public_id,
            'history' => $history->values()->all(),
        ]);

        $messages = $conversation->messages()
            ->where('role', '!=', 'system')
            ->orderBy('created_at')
            ->get()
            ->map(fn($message) => [
                'role' => $message->role,
                'content' => $message->content,
                'reasoning_content' => $message->reasoning_content,
                'blocks' => $message->blocks,
            ])
            ->all();

        $historyForBot = $this->historyForBot($request, $bot);

        $chatUrl = $conversation->chat_hash
            ? '/chat/' . $bot->slug . '/' . $conversation->chat_hash
            : null;

        return Inertia::render('ai/ChatBot', [
            'bot' => [
                'name' => $bot->name,
                'description' => $bot->description,
                'require_visitor_identity' => $bot->require_visitor_identity,
                'allowed_roles' => $bot->allowed_roles ?? [],
                'total_cost_usd' => (float) (AiConversation::query()
                    ->where('ai_chat_bot_id', $bot->id)
                    ->sum('usage_cost_usd') ?? 0),
            ],
            'messages' => $messages,
            'history' => $historyForBot,
            'messageUrl' => $this->routeUrlFor($bot, 'message'),
            'resetUrl' => $this->routeUrlFor($bot, 'reset'),
            'switchUrl' => $this->routeUrlFor($bot, 'switch'),
            'statusUrl' => $this->routeUrlFor($bot, 'status'),
            'warmupUrl' => $this->routeUrlFor($bot, 'warmup'),
            'showIdentityForm' => !$request->user()
                && $bot->require_visitor_identity
                && $conversation->messages()->where('role', '!=', 'system')->count() === 0,
            'chatHash' => $conversation->chat_hash,
            'chatUrl' => $chatUrl,
            'chatUrlBase' => '/chat/' . $bot->slug . '/',
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
