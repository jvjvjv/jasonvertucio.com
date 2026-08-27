<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAiSystemRequest;
use App\Http\Requests\UpdateAiSystemRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Jvjvjv\CodeTalker\Enums\AiProvider;
use Jvjvjv\CodeTalker\Models\AiInteractionLog;
use Jvjvjv\CodeTalker\Models\AiSystem;
use Jvjvjv\CodeTalker\Models\AiSystemFeatureDefault;
use Jvjvjv\CodeTalker\Models\AiSystemPrompt;
use Jvjvjv\CodeTalker\Services\AiModelReadinessService;
use Jvjvjv\CodeTalker\Services\AiSystemCapabilityService;
use Jvjvjv\CodeTalker\Services\ProviderModelsClient;

class AiSystemController extends Controller
{
    public function __construct(
        private AiSystemCapabilityService $aiSystemCapabilityService,
        private AiModelReadinessService $aiModelReadinessService,
        private ProviderModelsClient $providerModelsClient,
    ) {}

    /**
     * Display a list of all AI systems.
     */
    public function index(): InertiaResponse
    {
        $systems = AiSystem::withCount(['interactionLogs', 'personas as chat_bots_count'])
            ->with('featureDefaults')
            ->orderBy('name')
            ->get();

        $systems->each(function ($system) {
            $system->feature_defaults_list = $system->featureDefaults->pluck('feature')->toArray();
        });

        return Inertia::render('ai/systems/Index', [
            'systems' => $systems,
        ]);
    }

    /**
     * Show the form for creating a new AI system.
     */
    public function create(): InertiaResponse
    {
        $existingDefaults = AiSystemFeatureDefault::pluck('feature')->toArray();

        return Inertia::render('ai/systems/Create', [
            'existingDefaults' => $existingDefaults,
            'systemPrompts' => AiSystemPrompt::ordered()->get(['id', 'title', 'description', 'content']),
        ]);
    }

    /**
     * Store a newly created AI system.
     */
    public function store(StoreAiSystemRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $featureDefaults = $data['feature_defaults'] ?? [];
        unset($data['feature_defaults']);

        $this->resolveCustomSystemPrompt($data);
        // 'pricing_profile' is deprecated (no longer editable via the admin
        // UI, slated for removal) but stays in this list so an existing
        // value round-trips untouched rather than silently being dropped.
        $this->decodeJsonFields($data, ['config', 'credentials', 'pricing_profile', 'web_tool_policy']);
        $this->aiSystemCapabilityService->normalizeForPersistence($data);
        $this->aiSystemCapabilityService->hydrateForPersistence($data);

        $system = AiSystem::create($data);

        $this->syncFeatureDefaults($system, $featureDefaults);

        return redirect()->route('admin.ai.systems.index')
            ->with('success', "AI system \"{$system->name}\" created successfully.");
    }

    /**
     * Show the form for editing an AI system.
     */
    public function edit(AiSystem $aiSystem): InertiaResponse
    {
        $aiSystem->load('featureDefaults');
        $aiSystem->loadCount(['personas as chat_bots_count']);
        $aiSystem->feature_defaults_list = $aiSystem->featureDefaults->pluck('feature')->toArray();

        $existingDefaults = AiSystemFeatureDefault::where('ai_system_id', '!=', $aiSystem->id)
            ->pluck('feature')
            ->toArray();

        return Inertia::render('ai/systems/Edit', [
            'aiSystem' => $aiSystem,
            'existingDefaults' => $existingDefaults,
            'systemPrompts' => AiSystemPrompt::ordered()->get(['id', 'title', 'description', 'content']),
            'pendingFirstEdit' => $aiSystem->duplicated_at !== null,
        ]);
    }

    /**
     * Update the specified AI system.
     */
    public function update(UpdateAiSystemRequest $request, AiSystem $aiSystem): RedirectResponse
    {
        $data = $request->validated();
        $featureDefaults = $data['feature_defaults'] ?? [];
        unset($data['feature_defaults']);

        // A freshly duplicated system has never been through a real edit, so
        // its first save is allowed to change provider/model/API key just
        // like Create — after this save it locks like any other system.
        $isPendingFirstEdit = $aiSystem->duplicated_at !== null;

        $this->resolveCustomSystemPrompt($data);
        // 'pricing_profile' is deprecated (no longer editable via the admin
        // UI, slated for removal) but stays in this list so an existing
        // value round-trips untouched rather than silently being dropped.
        $this->decodeJsonFields($data, ['config', 'credentials', 'pricing_profile', 'web_tool_policy']);

        if (! $isPendingFirstEdit) {
            $data['provider'] = $aiSystem->provider;
            $data['model'] = $aiSystem->model;
        }

        if (! array_key_exists('base_url', $data) || blank($data['base_url'])) {
            $data['base_url'] = $aiSystem->base_url;
        }

        $this->aiSystemCapabilityService->normalizeForPersistence($data);
        $this->aiSystemCapabilityService->hydrateForPersistence($data);

        if (! $isPendingFirstEdit) {
            unset($data['provider'], $data['model']);
        }

        // duplicated_at is deliberately not fillable (see AiSystem model), so
        // it's cleared by direct property assignment rather than through the
        // mass-assigned $data array.
        $aiSystem->duplicated_at = null;
        $aiSystem->update($data);

        $this->syncFeatureDefaults($aiSystem, $featureDefaults);

        return redirect()->route('admin.ai.systems.index')
            ->with('success', "AI system \"{$aiSystem->name}\" updated successfully.");
    }

    /**
     * Remove the specified AI system.
     *
     * Deactivates any linked chat bots (preserving the relationship) and soft-deletes the system.
     */
    public function destroy(AiSystem $aiSystem): RedirectResponse
    {
        $name = $aiSystem->name;
        $botCount = $aiSystem->personas()->count();

        if ($botCount > 0) {
            $aiSystem->personas()->update(['is_active' => false]);
        }

        $aiSystem->delete();

        $message = $botCount > 0
            ? "AI system \"{$name}\" deleted. {$botCount} chat bot(s) were deactivated."
            : "AI system \"{$name}\" deleted successfully.";

        return redirect()->route('admin.ai.systems.index')
            ->with('success', $message);
    }

    /**
     * Display interaction logs for an AI system.
     */
    public function logs(AiSystem $aiSystem): InertiaResponse
    {
        $logs = AiInteractionLog::where('ai_system_id', $aiSystem->id)
            ->with('user')
            ->orderByDesc('created_at')
            ->paginate(50);

        $logs->getCollection()->transform(function ($log) {
            $log->created_at_formatted = $log->created_at->format('M j, Y g:i A');
            $log->user_name = $log->user?->name ?? 'System';

            return $log;
        });

        return Inertia::render('ai/systems/Logs', [
            'aiSystem' => $aiSystem,
            'logs' => $logs,
        ]);
    }

    /**
     * Fetch available models from a provider's API.
     */
    public function fetchModels(Request $request): JsonResponse
    {
        $request->validate([
            'provider' => ['required', 'string', Rule::in(AiProvider::values())],
            'api_key' => ['nullable', 'string'],
            'base_url' => ['nullable', 'string', 'url', 'max:255'],
        ]);

        try {
            $provider = AiProvider::from($request->string('provider')->toString());

            if ($provider->requiresApiKey() && blank($request->input('api_key'))) {
                return response()->json(['models' => [], 'error' => 'API key is required for this provider.'], 422);
            }

            $models = $this->providerModelsClient->listModels(
                $provider,
                $request->input('api_key') !== null ? (string) $request->input('api_key') : null,
                $request->string('base_url')->toString() ?: null,
            );

            $formatted = collect($models)->map(fn (array $m) => [
                'id' => $m['id'],
                'name' => $m['display_name'] ?? $m['id'],
                'loaded' => (bool) ($m['loaded'] ?? false),
                'max_context_length' => $m['max_context_length'] ?? null,
                'capabilities' => [
                    // Only LM Studio's model list reports capabilities at all; leaving
                    // `reasoning`/`tools` unset (rather than defaulting to false) for every
                    // other provider lets the frontend tell "unsupported" apart from
                    // "unknown" — all of Anthropic/OpenAI/Gemini/Grok support tool calling,
                    // so a blanket `false` there would be wrong, not just uninformative.
                    'reasoning' => data_get($m, 'capabilities.reasoning'),
                    'vision' => (bool) data_get($m, 'capabilities.vision', false),
                    'tools' => data_get($m, 'capabilities.tools'),
                ],
            ])->sortBy('name')->values()->toArray();

            return response()->json(['models' => $formatted]);

        } catch (\Exception $e) {
            return response()->json(['models' => [], 'error' => 'Failed to fetch models: '.$e->getMessage()], 422);
        }
    }

    /**
     * Return the readiness status of the model for an AI system.
     */
    public function modelStatus(AiSystem $aiSystem): JsonResponse
    {
        return response()->json([
            'status' => $this->aiModelReadinessService->statusForSystem($aiSystem),
        ]);
    }

    /**
     * Warm up (pre-load) the model for an AI system.
     */
    public function modelWarmup(AiSystem $aiSystem): JsonResponse
    {
        return response()->json([
            'status' => $this->aiModelReadinessService->warmUpSystem($aiSystem),
        ]);
    }

    /**
     * Duplicate an existing AI system.
     */
    public function duplicate(AiSystem $aiSystem): RedirectResponse
    {
        $clone = $aiSystem->replicate(['id']);
        $clone->name = $aiSystem->name.' (copy)';
        $clone->duplicated_at = now();
        $clone->save();

        return redirect()->route('admin.ai.systems.edit', $clone)
            ->with('success', 'AI system duplicated. Update the name and settings as needed.');
    }

    /**
     * If no system_prompt_id was provided but custom_system_prompt text was, create a new prompt record.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveCustomSystemPrompt(array &$data): void
    {
        $customText = $data['custom_system_prompt'] ?? null;
        unset($data['custom_system_prompt']);

        if (empty($data['system_prompt_id']) && ! empty($customText)) {
            $prompt = AiSystemPrompt::create([
                'title' => mb_substr(($data['name'] ?? 'AI System').' Custom Prompt', 0, 64),
                'description' => 'Custom prompt',
                'content' => $customText,
            ]);
            $data['system_prompt_id'] = $prompt->id;
        }
    }

    /**
     * Sync feature defaults for an AI system.
     *
     * @param  array<int, string>  $features
     */
    private function syncFeatureDefaults(AiSystem $system, array $features): void
    {
        // Remove existing defaults for this system
        AiSystemFeatureDefault::where('ai_system_id', $system->id)->delete();

        // Remove any existing defaults for these features (from other systems)
        if (! empty($features)) {
            AiSystemFeatureDefault::whereIn('feature', $features)->delete();
        }

        // Create new defaults
        foreach ($features as $feature) {
            AiSystemFeatureDefault::create([
                'ai_system_id' => $system->id,
                'feature' => $feature,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $fields
     */
    private function decodeJsonFields(array &$data, array $fields): void
    {
        foreach ($fields as $field) {
            if (! array_key_exists($field, $data) || $data[$field] === null || $data[$field] === '') {
                continue;
            }

            if (is_string($data[$field])) {
                $data[$field] = json_decode($data[$field], true);
            }
        }
    }
}
