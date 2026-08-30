<?php

namespace App\Http\Controllers;

use App\Models\AiChatBot;
use App\Models\AiConversation;
use App\Services\ChatBot\ChatBotAccessGuard;
use App\Services\ChatBot\ChatBotRouteUrls;
use App\Services\ChatBot\ChatBotSessionStore;
use App\Services\ChatBot\HostChatBotPagePayload;
use App\Services\ChatBot\PermissionFilteredChatBotIndexPayload;
use App\Services\ChatBot\PermissionFilteredChatBotStatusResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Jvjvjv\CodeTalker\Models\AiConversation as BaseAiConversation;
use Jvjvjv\CodeTalker\Services\AiPersonaConversationService;
use Jvjvjv\CodeTalker\Services\AiModelReadinessService;
use Jvjvjv\CodeTalker\Services\ChatBot\SseFrameEncoder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The host entry point for the chat-bot routes.
 *
 * code-talker 0.11.0 removed the package's own `ChatBotController` and every
 * collaborator it used to resolve from the container — the package is now a
 * pure service layer (`AiPersonaConversationService`, `SseFrameEncoder`,
 * `AiModelReadinessService`). This controller owns route dispatch, per-browser
 * conversation continuity, and SSE streaming outright, reproducing the removed
 * package controller's behavior (see `openspec/changes/upgrade-code-talker-0-11`).
 */
class ChatBotController extends Controller
{
    public function __construct(
        private AiPersonaConversationService $conversationService,
        private AiModelReadinessService $modelReadinessService,
        private ChatBotAccessGuard $accessGuard,
        private ChatBotSessionStore $sessions,
        private HostChatBotPagePayload $pagePayload,
        private PermissionFilteredChatBotIndexPayload $indexPayload,
        private PermissionFilteredChatBotStatusResolver $statusResolver,
        private ChatBotRouteUrls $urls,
        private SseFrameEncoder $sseEncoder,
    ) {
    }

    /**
     * Display the list of available chat bots.
     */
    public function index(Request $request): InertiaResponse
    {
        $this->sessions->forgetLegacyCookies($request);

        return Inertia::render('ai/ChatBotsIndex', [
            'bots' => $this->indexPayload->build($request->user()),
        ]);
    }

    public function statuses(): JsonResponse
    {
        return response()->json(['statuses' => $this->statusResolver->statusesBySlug()]);
    }

    /**
     * Display the chat bot page.
     */
    public function show(Request $request, AiChatBot $aiChatBot): InertiaResponse
    {
        $this->accessGuard->authorize($request, $aiChatBot);

        $conversation = $this->sessions->currentConversation($request, $aiChatBot);

        return Inertia::render('ai/ChatBot', $this->pagePayload->build(
            $aiChatBot,
            $conversation,
            $this->sessions->state($request, $aiChatBot)['history'],
            // Without a conversation there is nobody to attribute the chat to yet.
            showIdentityForm: ! $request->user()
                && $aiChatBot->require_visitor_identity
                && $conversation === null,
        ));
    }

    /**
     * Load a conversation by its hash or UUID (UUID is the fallback for direct linking).
     * This allows accessing a specific chat from any computer.
     */
    public function showByHash(Request $request, string $slug, string $hash): InertiaResponse
    {
        $conversation = AiConversation::findByChatHashOrUuid($hash);

        if ($conversation === null) {
            abort(404);
        }

        $bot = $conversation->aiPersona;

        $this->accessGuard->authorize($request, $bot);

        // Adopt the linked conversation as the current one for this browser.
        $this->sessions->remember($request, $bot, $conversation);

        return Inertia::render('ai/ChatBot', $this->pagePayload->build(
            $bot,
            $conversation,
            $this->sessions->state($request, $bot)['history'],
            // The conversation already exists here, so the identity form keys
            // off whether anything has actually been said in it.
            showIdentityForm: ! $request->user()
                && $bot->require_visitor_identity
                && $conversation->messages()->where('role', '!=', 'system')->count() === 0,
            includeChatHash: true,
        ));
    }

    public function status(Request $request, AiChatBot $aiChatBot): JsonResponse
    {
        $this->accessGuard->authorize($request, $aiChatBot);

        return response()->json([
            'status' => $this->modelReadinessService->statusForPersona($aiChatBot),
        ]);
    }

    public function warmup(Request $request, AiChatBot $aiChatBot): JsonResponse
    {
        $this->accessGuard->authorize($request, $aiChatBot);

        return response()->json([
            'status' => $this->modelReadinessService->warmUpPersona($aiChatBot),
        ]);
    }

    /**
     * Stream a response from the configured chat bot.
     */
    public function message(Request $request, AiChatBot $aiChatBot): StreamedResponse
    {
        $this->accessGuard->authorize($request, $aiChatBot);

        $validated = $request->validate([
            'message' => ['required', 'string'],
        ]);

        $conversation = $this->sessions->currentConversation($request, $aiChatBot)
            ?? $this->startConversation($request, $aiChatBot);

        // Always regenerate the hash to ensure it uses the current encoding format.
        // generateChatHash() is deterministic (same inputs → same output), so this
        // is safe and also migrates any stale hashes stored by old encode versions.
        $chatHash = $conversation->generateChatHash();

        $message = $validated['message'];

        // Tool arguments/results can carry whatever the model or a fetched
        // page put in them — including a credential the model is handling on
        // the visitor's behalf, for a system with allow_credential_headers
        // enabled. Debugging aid only; never expose this in production.
        if (! app()->environment('production')) {
            $this->conversationService->usingToolPayloads();
        }

        $events = $this->conversationService->continueConversation($conversation, $message);

        $response = new StreamedResponse(function () use ($events): void {
            // connection_aborted() (used by the conversation service's default
            // cancellation check) only reports the client disconnect once output
            // has been flushed to a dead connection *and* PHP is told not to kill
            // the script outright on that disconnect — hence this call.
            ignore_user_abort(true);

            foreach ($this->sseEncoder->encode($events) as $frame) {
                echo $frame;
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('X-Accel-Buffering', 'no');
        $response->headers->set('X-Chat-Hash', $chatHash);

        return $response;
    }

    public function switch(Request $request, AiChatBot $aiChatBot): RedirectResponse
    {
        $this->accessGuard->authorize($request, $aiChatBot);

        $validated = $request->validate([
            'conversation' => ['required', 'string'],
        ]);

        abort_unless($this->sessions->switchTo($request, $aiChatBot, $validated['conversation']), 404);

        return redirect($this->urls->for($aiChatBot, 'show'));
    }

    /**
     * Start a new chat while preserving prior conversation history for this browser.
     */
    public function reset(Request $request, AiChatBot $aiChatBot): RedirectResponse
    {
        return $this->newChat($request, $aiChatBot);
    }

    /**
     * Start a new chat conversation (resets session and redirects to show).
     */
    public function newChat(Request $request, AiChatBot $aiChatBot): RedirectResponse
    {
        $this->accessGuard->authorize($request, $aiChatBot);

        $this->sessions->startNewChat($request, $aiChatBot);

        return redirect($this->urls->for($aiChatBot, 'show'));
    }

    /**
     * A first message with no conversation in session opens one, collecting the
     * visitor's identity first when the bot asks for it.
     */
    private function startConversation(Request $request, AiChatBot $aiChatBot): BaseAiConversation
    {
        if ($aiChatBot->require_visitor_identity && ! $request->user()) {
            $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
            ]);
        }

        $conversation = $this->conversationService->startConversation(
            persona: $aiChatBot,
            user: $request->user(),
            visitorName: $request->string('name')->toString() ?: null,
            visitorEmail: $request->string('email')->toString() ?: null,
        );

        $this->sessions->remember($request, $aiChatBot, $conversation);

        return $conversation;
    }
}
