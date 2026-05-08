<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAiChatBotRequest;
use App\Http\Requests\Admin\UpdateAiChatBotRequest;
use App\Models\AiChatBot;
use App\Models\AiSystem;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Spatie\Permission\Models\Role;

class AiChatBotController extends Controller
{
    /**
     * Display a list of AI chat bots.
     */
    public function index(): InertiaResponse
    {
        $bots = AiChatBot::query()
            ->with('aiSystem')
            ->withCount('conversations')
            ->withSum('conversations', 'usage_input_tokens')
            ->withSum('conversations', 'usage_output_tokens')
            ->withSum('conversations', 'usage_cost_usd')
            ->orderBy('name')
            ->get()
            ->map(fn (AiChatBot $bot) => [
                'id' => $bot->id,
                'name' => $bot->name,
                'slug' => $bot->slug,
                'access_path' => $bot->access_path,
                'public_url' => $bot->publicPath(),
                'description' => $bot->description,
                'allowed_roles' => $bot->allowed_roles ?? [],
                'is_active' => $bot->is_active,
                'is_public' => $bot->is_public,
                'require_visitor_identity' => $bot->require_visitor_identity,
                'conversations_count' => $bot->conversations_count,
                'ai_system_name' => $bot->aiSystem?->name,
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
     * Soft delete the specified bot.
     */
    public function destroy(AiChatBot $aiChatBot): RedirectResponse
    {
        $name = $aiChatBot->name;
        $aiChatBot->delete();

        return redirect()->route('admin.ai.bots.index')
            ->with('success', "AI chat bot \"{$name}\" deleted successfully.");
    }

    /**
     * @return array<int, array{id: int, name: string, model: string}>
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
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function roles(): array
    {
        return Role::query()->orderBy('name')->pluck('name')->all();
    }
}
