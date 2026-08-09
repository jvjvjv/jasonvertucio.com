<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\ProvidesAdminNavigation;
use App\Models\AiChatBot;
use BSPDX\Keystone\Models\KeystoneRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Jvjvjv\CodeTalker\Http\Controllers\Admin\AiChatBotController as BaseAiChatBotController;
use Jvjvjv\CodeTalker\Http\Requests\Admin\StoreAiChatBotRequest as BaseStoreAiChatBotRequest;
use Jvjvjv\CodeTalker\Http\Requests\Admin\UpdateAiChatBotRequest as BaseUpdateAiChatBotRequest;
use Jvjvjv\CodeTalker\Models\AiChatBot as BaseAiChatBot;
use Jvjvjv\CodeTalker\Models\AiSystem;

/**
 * Extends the package controller, overriding only what the host adds on top:
 * admin nav blocks, Keystone role selection, and the `allowed_roles` field.
 *
 * `destroy()` and `mcpTools()` are inherited unchanged. Every other method is
 * overridden because it touches `allowed_roles`, which exists only on the
 * host's AiChatBot model and form requests — inheriting them would silently
 * drop the field on write and omit it on read.
 */
class AiChatBotController extends BaseAiChatBotController
{
    use ProvidesAdminNavigation;

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
    public function store(BaseStoreAiChatBotRequest $request): RedirectResponse
    {
        $bot = AiChatBot::create($request->validated());

        return redirect()->route('admin.ai.bots.index')
            ->with('success', "AI chat bot \"{$bot->name}\" created successfully.");
    }

    /**
     * Show the form for editing a bot.
     */
    public function edit(BaseAiChatBot $aiChatBot): InertiaResponse
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
    public function update(BaseUpdateAiChatBotRequest $request, BaseAiChatBot $aiChatBot): RedirectResponse
    {
        $aiChatBot->update($request->validated());

        return redirect()->route('admin.ai.bots.index')
            ->with('success', "AI chat bot \"{$aiChatBot->name}\" updated successfully.");
    }

    /**
     * @return array<int, string>
     */
    private function roles(): array
    {
        return KeystoneRole::query()->orderBy('name')->pluck('name')->all();
    }

    /**
     * Mirrors the package's private systems() helper, which subclasses cannot
     * reach.
     *
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
