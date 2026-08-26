<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\ProvidesAdminNavigation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAiChatBotRequest;
use App\Http\Requests\Admin\UpdateAiChatBotRequest;
use App\Models\AiChatBot;
use BSPDX\Keystone\Models\KeystoneRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Jvjvjv\CodeTalker\Models\AiSystem;
use Jvjvjv\CodeTalker\Services\Management\AiChatBotManager;

/**
 * code-talker 0.11.0 removed the package's admin `AiChatBotController` — this
 * controller is now fully host-owned, built on `AiChatBotManager` for the
 * write operations (create/update/delete) and `allowed_roles`/Keystone role
 * selection remaining host-only concerns.
 */
class AiChatBotController extends Controller
{
    use ProvidesAdminNavigation;

    public function __construct(private AiChatBotManager $bots)
    {
    }

    /**
     * Display a list of AI chat bots.
     */
    public function index(Request $request): InertiaResponse
    {
        $aiSystemId = $request->query('ai_system_id');

        $bots = AiChatBot::query()
            ->with('aiSystem')
            ->withCount('conversations')
            ->withSum('conversations', 'usage_input_tokens')
            ->withSum('conversations', 'usage_output_tokens')
            ->withSum('conversations', 'usage_cost_usd')
            ->when($aiSystemId, fn ($q) => $q->where('ai_system_id', $aiSystemId))
            ->orderBy('name')
            ->get()
            ->map(fn (AiChatBot $bot) => [
                'id' => $bot->id,
                'name' => $bot->name,
                'slug' => $bot->slug,
                'access_path' => $bot->access_path,
                'public_url' => $bot->publicPath(),
                'allowed_roles' => $bot->allowed_roles ?? [],
                'description' => $bot->description,
                'is_active' => $bot->is_active,
                'ai_system' => $bot->aiSystem,
                'require_visitor_identity' => $bot->require_visitor_identity,
                'conversations_count' => $bot->conversations_count,
                '_id' => $bot->aiSystemId,
                'tools_enabled' => $bot->tools_enabled,
                'usage' => $bot->conversations_sum_usage_cost_usd !== null ? [
                    'input_tokens' => (int) ($bot->conversations_sum_usage_input_tokens ?? 0),
                    'output_tokens' => (int) ($bot->conversations_sum_usage_output_tokens ?? 0),
                    'total_tokens' => null,
                    'cost_usd' => (float) $bot->conversations_sum_usage_cost_usd,
                    'synced_at' => null,
                ] : null,
            ]);

        return Inertia::render('ai/bots/Index', [
            'bots' => $bots,
            'filters' => ['ai_system_id' => $aiSystemId],
            'navBlocks' => $this->navBlocksFor('/admin/ai/chat-bots', $request),
        ]);
    }

    /**
     * Show the form for creating a bot.
     */
    public function create(): InertiaResponse
    {
        return Inertia::render('ai/bots/Create', [
            'systems' => $this->systems(),
            'roles' => $this->roles(),
        ]);
    }

    /**
     * Store a newly created bot.
     */
    public function store(StoreAiChatBotRequest $request): RedirectResponse
    {
        $bot = AiChatBot::create($request->validated());

        return redirect()->route('admin.ai.bots.index')
            ->with('success', "AI chat bot \"{$bot->name}\" created successfully.");
    }

    /**
     * Show the form for editing a bot.
     */
    public function edit(AiChatBot $aiChatBot): InertiaResponse
    {
        $aiChatBot->loadCount('conversations');

        return Inertia::render('ai/bots/Edit', [
            'bot' => $aiChatBot,
            'systems' => $this->systems(),
            'roles' => $this->roles(),
        ]);
    }

    /**
     * Update the specified bot.
     */
    public function update(UpdateAiChatBotRequest $request, AiChatBot $aiChatBot): RedirectResponse
    {
        $aiChatBot->update($request->validated());

        return redirect()->route('admin.ai.bots.index')
            ->with('success', "AI chat bot \"{$aiChatBot->name}\" updated successfully.");
    }

    /**
     * Delete the specified bot.
     */
    public function destroy(AiChatBot $aiChatBot): RedirectResponse
    {
        $name = $aiChatBot->name;

        $this->bots->delete($aiChatBot);

        return redirect()->route('admin.ai.bots.index')
            ->with('success', "AI chat bot \"{$name}\" deleted successfully.");
    }

    /**
     * The tools available to bots on an AI system, for the tool picker.
     */
    public function mcpTools(Request $request): JsonResponse
    {
        $request->validate([
            'ai_system_id' => ['nullable', 'integer', 'exists:ai_systems,id'],
            'include_all' => ['nullable', 'boolean'],
        ]);

        $tools = $this->bots->availableTools(
            aiSystemId: $request->filled('ai_system_id') ? $request->integer('ai_system_id') : null,
            includeAll: $request->boolean('include_all'),
            userId: $request->user()?->getKey(),
        );

        return response()->json(['tools' => $tools]);
    }

    /**
     * @return array<int, string>
     */
    private function roles(): array
    {
        return KeystoneRole::query()->orderBy('name')->pluck('name')->all();
    }

    /**
     * @return array<int, array{id: int, name: string, model: string, context_length: int|null, temperature: float|null, supports_tools: bool}>
     */
    private function systems(): array
    {
        return AiSystem::query()
            ->active()
            ->orderBy('name')
            ->get()
            ->map(fn (AiSystem $system) => [
                'id' => $system->id,
                'name' => $system->name,
                'model' => $system->model,
                'context_length' => $system->context_length,
                'temperature' => $system->temperature !== null ? (float) $system->temperature : null,
                'supports_tools' => (bool) $system->supports_tools,
            ])
            ->all();
    }
}
