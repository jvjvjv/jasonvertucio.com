<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendAiChatBotMessageRequest;
use App\Models\AiChatBot;
use App\Models\AiConversation;
use App\Services\AiChatBotConversationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatBotController extends Controller
{
    private const COOKIE_MINUTES = 60 * 24 * 180;

    public function __construct(
        private AiChatBotConversationService $conversationService,
    ) {
    }

    /**
     * Display the chat bot page.
     */
    public function show(Request $request, AiChatBot $aiChatBot): InertiaResponse
    {
        $this->abortIfInaccessible($request, $aiChatBot);

        $conversation = $this->storedConversation($request, $aiChatBot);
        $history = $this->historyForBot($request, $aiChatBot);
        $messages = [];

        if ($conversation !== null) {
            $messages = $conversation->messages()
                ->where('role', '!=', 'system')
                ->orderBy('created_at')
                ->get()
                ->map(fn ($message) => [
                    'role' => $message->role,
                    'content' => $message->content,
                ])
                ->all();
        }

        return Inertia::render('ai/ChatBot', [
            'bot' => [
                'name' => $aiChatBot->name,
                'description' => $aiChatBot->description,
                'is_public' => $aiChatBot->is_public,
                'require_visitor_identity' => $aiChatBot->require_visitor_identity,
            ],
            'messages' => $messages,
            'history' => $history,
            'messageUrl' => $this->routeUrlFor($aiChatBot, 'message'),
            'resetUrl' => $this->routeUrlFor($aiChatBot, 'reset'),
            'switchUrl' => $this->routeUrlFor($aiChatBot, 'switch'),
            'showIdentityForm' => !$request->user()
                && $aiChatBot->require_visitor_identity
                && $conversation === null,
        ]);
    }

    /**
     * Stream a response from the configured chat bot.
     */
    public function message(SendAiChatBotMessageRequest $request, AiChatBot $aiChatBot): StreamedResponse
    {
        $this->abortIfInaccessible($request, $aiChatBot);

        $conversation = $this->storedConversation($request, $aiChatBot);

        if ($conversation === null) {
            if ($aiChatBot->require_visitor_identity && !$request->user()) {
                $request->validate([
                    'name' => ['required', 'string', 'max:255'],
                    'email' => ['required', 'email', 'max:255'],
                ]);
            }

            $conversation = $this->conversationService->startConversation(
                bot: $aiChatBot,
                user: $request->user(),
                visitorName: $request->string('name')->toString() ?: null,
                visitorEmail: $request->string('email')->toString() ?: null,
            );

            $this->rememberConversation($request, $aiChatBot, $conversation);
        }

        return response()->stream(function () use ($request, $conversation) {
            $generator = $this->conversationService->continueConversation(
                $conversation,
                $request->validated('message'),
            );

            foreach ($generator as $chunk) {
                echo $chunk;
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function switch(Request $request, AiChatBot $aiChatBot): RedirectResponse
    {
        $this->abortIfInaccessible($request, $aiChatBot);

        $validated = $request->validate([
            'conversation' => ['required', 'string'],
        ]);

        $state = $this->storedState($request, $aiChatBot);
        $match = collect($state['history'] ?? [])->firstWhere('handle', $validated['conversation']);

        abort_unless($match !== null, 404);

        $state['current'] = $match['public_id'];
        $this->putStoredState($request, $aiChatBot, $state);

        return redirect($this->routeUrlFor($aiChatBot, 'show'));
    }

    /**
    * Start a new chat while preserving prior conversation history for this browser.
     */
    public function reset(Request $request, AiChatBot $aiChatBot): RedirectResponse
    {
        $this->abortIfInaccessible($request, $aiChatBot);

        $state = $this->storedState($request, $aiChatBot);
        $state['current'] = null;
        $this->putStoredState($request, $aiChatBot, $state);

        return redirect($this->routeUrlFor($aiChatBot, 'show'));
    }

    private function abortIfInaccessible(Request $request, AiChatBot $aiChatBot): void
    {
        abort_unless($aiChatBot->is_active, 404);
        abort_unless($aiChatBot->access_path === $this->requestAccessPath($request), 404);

        if ($request->user()) {
            abort_unless($aiChatBot->is_public || $aiChatBot->allowsRole($request->user()), 403);

            return;
        }

        abort_unless($aiChatBot->is_public, 403);
    }

    private function storedConversation(Request $request, AiChatBot $aiChatBot): ?AiConversation
    {
        $conversationPublicId = data_get($this->storedState($request, $aiChatBot), 'current');

        if ($conversationPublicId === null) {
            return null;
        }

        $conversation = AiConversation::query()
            ->where('public_id', $conversationPublicId)
            ->where('ai_chat_bot_id', $aiChatBot->id)
            ->with('messages')
            ->first();

        if ($conversation === null) {
            $this->clearStoredState($request, $aiChatBot);

            return null;
        }

        if ($conversation->user_id !== null && $conversation->user_id !== $request->user()?->id) {
            $this->clearStoredState($request, $aiChatBot);

            return null;
        }

        return $conversation;
    }

    /**
     * @return array<int, array{handle: string, label: string, is_current: bool, updated_at: string}>
     */
    private function historyForBot(Request $request, AiChatBot $aiChatBot): array
    {
        $state = $this->storedState($request, $aiChatBot);
        $historyItems = collect($state['history'] ?? []);

        if ($historyItems->isEmpty()) {
            return [];
        }

        $conversations = AiConversation::query()
            ->where('ai_chat_bot_id', $aiChatBot->id)
            ->whereIn('public_id', $historyItems->pluck('public_id')->all())
            ->orderByLastMessageAtDesc()
            ->get()
            ->keyBy('public_id');

        return $historyItems
            ->map(function (array $item) use ($conversations, $state): ?array {
                /** @var AiConversation|null $conversation */
                $conversation = $conversations->get($item['public_id']);

                if ($conversation === null) {
                    return null;
                }

                $label = trim((string) ($conversation->title ?: 'New chat'));

                return [
                    'handle' => $item['handle'],
                    'label' => $label,
                    'is_current' => ($state['current'] ?? null) === $conversation->public_id,
                    'updated_at' => $conversation->last_message_at?->diffForHumans()
                        ?? $conversation->updated_at?->diffForHumans()
                        ?? 'just now',
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array{current: ?string, history: array<int, array{handle: string, public_id: string}>}
     */
    private function storedState(Request $request, AiChatBot $aiChatBot): array
    {
        $state = $request->session()->get($this->stateKey($aiChatBot));

        if (!is_array($state)) {
            $decoded = json_decode((string) $request->cookie($this->stateKey($aiChatBot), '[]'), true);
            $state = is_array($decoded) ? $decoded : [];
            $request->session()->put($this->stateKey($aiChatBot), $state);
        }

        return [
            'current' => is_string($state['current'] ?? null) ? $state['current'] : null,
            'history' => collect($state['history'] ?? [])
                ->filter(fn (mixed $item) => is_array($item) && is_string($item['handle'] ?? null) && is_string($item['public_id'] ?? null))
                ->values()
                ->all(),
        ];
    }

    /**
     * @param array{current: ?string, history: array<int, array{handle: string, public_id: string}>} $state
     */
    private function putStoredState(Request $request, AiChatBot $aiChatBot, array $state): void
    {
        $request->session()->put($this->stateKey($aiChatBot), $state);
        Cookie::queue(cookie()->make(
            $this->stateKey($aiChatBot),
            json_encode($state, JSON_THROW_ON_ERROR),
            self::COOKIE_MINUTES,
            secure: request()->isSecure(),
            httpOnly: true,
            sameSite: 'lax',
        ));
    }

    private function rememberConversation(Request $request, AiChatBot $aiChatBot, AiConversation $conversation): void
    {
        $state = $this->storedState($request, $aiChatBot);
        $history = collect($state['history']);

        if (!$history->contains(fn (array $item) => $item['public_id'] === $conversation->public_id)) {
            $history->prepend([
                'handle' => (string) Str::ulid(),
                'public_id' => $conversation->public_id,
            ]);
        }

        $this->putStoredState($request, $aiChatBot, [
            'current' => $conversation->public_id,
            'history' => $history->values()->all(),
        ]);
    }

    private function clearStoredState(Request $request, AiChatBot $aiChatBot): void
    {
        $request->session()->forget($this->stateKey($aiChatBot));
        Cookie::queue(Cookie::forget($this->stateKey($aiChatBot)));
    }

    private function stateKey(AiChatBot $aiChatBot): string
    {
        return 'ai_chat_bot_conversations_' . $aiChatBot->id;
    }

    private function requestAccessPath(Request $request): string
    {
        return $request->routeIs('chat-bots.root.*')
            ? AiChatBot::ACCESS_PATH_ROOT
            : AiChatBot::ACCESS_PATH_CHAT;
    }

    private function routeUrlFor(AiChatBot $aiChatBot, string $action): string
    {
        $prefix = $aiChatBot->usesRootAccessPath() ? 'chat-bots.root.' : 'chat-bots.chat.';

        return route($prefix . $action, $aiChatBot);
    }
}
