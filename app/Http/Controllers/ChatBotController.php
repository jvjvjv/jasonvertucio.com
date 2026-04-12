<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendAiChatBotMessageRequest;
use App\Models\AiChatBot;
use App\Models\AiConversation;
use App\Services\AiChatBotConversationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatBotController extends Controller
{
    public function __construct(
        private AiChatBotConversationService $conversationService,
    ) {
    }

    /**
     * Display the chat bot page.
     */
    public function show(Request $request, AiChatBot $aiChatBot): View
    {
        $this->abortIfInaccessible($request, $aiChatBot);

        $conversation = $this->sessionConversation($request, $aiChatBot);
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

        return view('ai.chat-bot', [
            'bot' => $aiChatBot,
            'messages' => $messages,
            'conversation' => $conversation,
        ]);
    }

    /**
     * Stream a response from the configured chat bot.
     */
    public function message(SendAiChatBotMessageRequest $request, AiChatBot $aiChatBot): StreamedResponse
    {
        $this->abortIfInaccessible($request, $aiChatBot);

        $conversation = $this->sessionConversation($request, $aiChatBot);

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

            $request->session()->put($this->sessionKey($aiChatBot), $conversation->id);
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

    /**
     * Reset the current session conversation for a bot.
     */
    public function reset(Request $request, AiChatBot $aiChatBot): \Illuminate\Http\RedirectResponse
    {
        $this->abortIfInaccessible($request, $aiChatBot);
        $request->session()->forget($this->sessionKey($aiChatBot));

        return redirect()->route('chat-bots.show', $aiChatBot);
    }

    private function abortIfInaccessible(Request $request, AiChatBot $aiChatBot): void
    {
        abort_unless($aiChatBot->is_active, 404);

        if ($request->user()) {
            abort_unless($aiChatBot->is_public || $aiChatBot->allowsRole($request->user()), 403);

            return;
        }

        abort_unless($aiChatBot->is_public, 403);
    }

    private function sessionConversation(Request $request, AiChatBot $aiChatBot): ?AiConversation
    {
        $conversationId = $request->session()->get($this->sessionKey($aiChatBot));

        if ($conversationId === null) {
            return null;
        }

        $conversation = AiConversation::query()
            ->whereKey($conversationId)
            ->where('ai_chat_bot_id', $aiChatBot->id)
            ->with('messages')
            ->first();

        if ($conversation === null) {
            return null;
        }

        if ($conversation->user_id !== null && $conversation->user_id !== $request->user()?->id) {
            $request->session()->forget($this->sessionKey($aiChatBot));

            return null;
        }

        return $conversation;
    }

    private function sessionKey(AiChatBot $aiChatBot): string
    {
        return 'ai_chat_bot_conversation.' . $aiChatBot->id;
    }
}
