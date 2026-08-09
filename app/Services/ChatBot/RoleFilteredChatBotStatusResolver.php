<?php

namespace App\Services\ChatBot;

use App\Models\AiChatBot;
use App\Models\User;
use Illuminate\Http\Request;
use Jvjvjv\CodeTalker\Services\AiModelReadinessService;
use Jvjvjv\CodeTalker\Services\ChatBot\ChatBotStatusResolver;

/**
 * Readiness statuses keyed by slug, narrowed to the bots this viewer's roles allow.
 *
 * Mirrors the role filtering in RoleFilteredChatBotIndexPayload so the statuses
 * endpoint cannot disclose bots the index already hides. Bots commonly share an
 * AiSystem and a readiness check can reach the provider, so each system is only
 * checked once per request.
 *
 * `$modelReadiness` is re-declared because the parent's copy is private; the
 * parent's `statusesBySlug()` takes no arguments, so the viewer arrives via the
 * injected request instead.
 */
class RoleFilteredChatBotStatusResolver extends ChatBotStatusResolver
{
    public function __construct(
        private AiModelReadinessService $modelReadiness,
        private Request $request,
    ) {
        parent::__construct($modelReadiness);
    }

    /**
     * @return array<string, mixed>
     */
    public function statusesBySlug(): array
    {
        $user = $this->request->user();

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
                $statusesBySystemId[$bot->ai_system_id] = $this->modelReadiness->statusForSystem($bot->aiSystem);
            }

            $statusesByBotSlug[$bot->slug] = $statusesBySystemId[$bot->ai_system_id];
        }

        return $statusesByBotSlug;
    }

    /**
     * A bot with no `allowed_roles` is public; otherwise the viewer must hold one.
     */
    private function canAccess(AiChatBot $bot, ?User $user): bool
    {
        return empty($bot->allowed_roles) || $bot->allowsRole($user);
    }
}
