<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAiSystemRequest;
use App\Http\Requests\UpdateAiSystemRequest;
use App\Services\AiSystemPresenter;
use App\Services\AiSystemSecretMerger;
use App\Services\ChatBot\McpServerCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Jvjvjv\CodeTalker\Enums\AiProvider;
use Jvjvjv\CodeTalker\Enums\AiSystemRole;
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
        private McpServerCatalog $mcpServers,
        private AiSystemPresenter $presenter,
        private AiSystemSecretMerger $secrets,
    ) {}

    /**
     * Display the chat systems. Voice systems have their own page
     * (VoiceSystemController).
     */
    public function index(): InertiaResponse
    {
        $systems = AiSystem::withCount(['interactionLogs', 'personas as chat_bots_count'])
            ->role(AiSystemRole::Chat)
            ->with('featureDefaults')
            ->orderBy('name')
            ->get();

        $systems->each(function ($system) {
            $system->feature_defaults_list = $system->featureDefaults->pluck('feature')->toArray();
        });

        return Inertia::render('ai/systems/Index', [
            'systems' => $systems->map(fn (AiSystem $system) => $this->presenter->present($system))->values(),
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
            'mcpServers' => $this->mcpServers->forToolPicker(),
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
        $role = AiSystemRole::from($data['role']);
        $this->applyRoleFields($data, $role, settingModel: true);
        $this->aiSystemCapabilityService->normalizeForPersistence($data);

        if ($role === AiSystemRole::Chat) {
            $this->aiSystemCapabilityService->hydrateForPersistence($data);
        }

        $system = AiSystem::create($data);

        $this->syncFeatureDefaults($system, $featureDefaults);

        return redirect()->route(self::indexRouteFor($role))
            ->with('success', "AI system \"{$system->name}\" created successfully.");
    }

    /**
     * Show the form for editing an AI system.
     */
    public function edit(AiSystem $aiSystem): InertiaResponse|RedirectResponse
    {
        if (self::isVoice($aiSystem)) {
            return redirect()->route('admin.ai.voice-systems.edit', $aiSystem);
        }

        $aiSystem->load('featureDefaults');
        $aiSystem->loadCount(['personas as chat_bots_count']);
        $aiSystem->feature_defaults_list = $aiSystem->featureDefaults->pluck('feature')->toArray();

        $existingDefaults = AiSystemFeatureDefault::where('ai_system_id', '!=', $aiSystem->id)
            ->pluck('feature')
            ->toArray();

        return Inertia::render('ai/systems/Edit', [
            'aiSystem' => $this->presenter->present($aiSystem),
            'existingDefaults' => $existingDefaults,
            'systemPrompts' => AiSystemPrompt::ordered()->get(['id', 'title', 'description', 'content']),
            'pendingFirstEdit' => $aiSystem->duplicated_at !== null,
            'mcpServers' => $this->mcpServers->forToolPicker(),
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
        $this->applySecretWrites($aiSystem, $data);

        $role = $isPendingFirstEdit
            ? AiSystemRole::from($data['role'])
            : ($aiSystem->role ?? AiSystemRole::Chat);
        $this->applyRoleFields($data, $role, settingModel: $isPendingFirstEdit, storedConfig: $aiSystem->config);

        if (! $isPendingFirstEdit) {
            $data['provider'] = $aiSystem->provider;
            $data['model'] = $aiSystem->model;
        }

        if (! array_key_exists('base_url', $data) || blank($data['base_url'])) {
            $data['base_url'] = $aiSystem->base_url;
        }

        $this->aiSystemCapabilityService->normalizeForPersistence($data);

        if ($role === AiSystemRole::Chat) {
            $this->aiSystemCapabilityService->hydrateForPersistence($data);
        }

        if (! $isPendingFirstEdit) {
            unset($data['provider'], $data['model']);
        }

        // duplicated_at is deliberately not fillable (see AiSystem model), so
        // it's cleared by direct property assignment rather than through the
        // mass-assigned $data array.
        $aiSystem->duplicated_at = null;
        $aiSystem->update($data);

        $this->syncFeatureDefaults($aiSystem, $featureDefaults);

        return redirect()->route(self::indexRouteFor($role))
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

        return redirect()->route(self::indexRouteFor($aiSystem->role ?? AiSystemRole::Chat))
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
            'aiSystem' => $this->presenter->present($aiSystem),
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
            'ai_system_id' => ['nullable', 'integer', Rule::exists('ai_systems', 'id')->whereNull('deleted_at')],
        ]);

        try {
            $provider = AiProvider::from($request->string('provider')->toString());
            $apiKey = $request->input('api_key') !== null ? (string) $request->input('api_key') : null;
            $baseUrl = $request->string('base_url')->toString() ?: null;

            if (blank($apiKey) && $request->filled('ai_system_id')) {
                $stored = $this->storedKeyFor($request, $provider, $baseUrl);

                if ($stored instanceof JsonResponse) {
                    return $stored;
                }

                $apiKey = $stored;
            }

            if ($provider->requiresApiKey() && blank($apiKey)) {
                return response()->json(['models' => [], 'error' => 'API key is required for this provider.'], 422);
            }

            $models = $this->providerModelsClient->listModels($provider, $apiKey, $baseUrl);

            $formatted = collect($models)->map(fn (array $m) => [
                'id' => $m['id'],
                'name' => $m['display_name'] ?? $m['id'],
                'loaded' => (bool) ($m['loaded'] ?? false),
                'max_context_length' => $m['max_context_length'] ?? null,
                // Only present for providers that report a local model file's size on
                // disk (currently LM Studio's native API); null everywhere else.
                'size_bytes' => $m['size_bytes'] ?? null,
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
     * The stored key of the system a model lookup names, or a 422 when using it
     * would send it somewhere other than where it was configured for.
     *
     * Without this check, a lookup naming a system plus a different provider or
     * base URL would deliver the stored key to an address the caller chose.
     */
    private function storedKeyFor(Request $request, AiProvider $provider, ?string $baseUrl): string|JsonResponse
    {
        $system = AiSystem::query()->findOrFail($request->integer('ai_system_id'));

        $sameEndpoint = $system->provider === $provider->value
            && $this->sameBaseUrl($baseUrl, $system->base_url);

        if (! $sameEndpoint) {
            return response()->json([
                'models' => [],
                'error' => 'The provider or base URL differs from the saved system. Enter the API key to use it.',
            ], 422);
        }

        return (string) $system->api_key;
    }

    /**
     * Whether two base URLs name the same endpoint. An absent URL means the
     * provider default, so it only matches another absent URL — never a custom
     * one, or the stored key would be sent to the default provider instead.
     */
    private function sameBaseUrl(?string $requested, ?string $stored): bool
    {
        $normalize = static fn (?string $url): string => rtrim(strtolower(trim((string) $url)), '/');

        return $normalize($requested) === $normalize($stored);
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
     * Voice systems are listed and edited on their own pages.
     */
    public static function isVoice(AiSystem $system): bool
    {
        return ($system->role ?? AiSystemRole::Chat) !== AiSystemRole::Chat;
    }

    private static function indexRouteFor(AiSystemRole $role): string
    {
        return $role === AiSystemRole::Chat ? 'admin.ai.systems.index' : 'admin.ai.voice-systems.index';
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
     * Stored secrets are never sent to the browser, so the form cannot resend
     * them: an empty secret field keeps the stored value, a value replaces it,
     * and the `clear_*` flags remove it.
     *
     * @param  array<string, mixed>  $data
     */
    private function applySecretWrites(AiSystem $system, array &$data): void
    {
        $clearApiKey = (bool) ($data['clear_api_key'] ?? false);
        $clearCredentials = (bool) ($data['clear_credentials'] ?? false);
        unset($data['clear_api_key'], $data['clear_credentials']);

        $data['api_key'] = $this->secrets->apiKey((string) $system->api_key, $data['api_key'] ?? null, $clearApiKey);
        $data['credentials'] = $this->secrets->credentials($system->credentials, $data['credentials'] ?? null, $clearCredentials);

        // An omitted policy leaves the stored one alone; a submitted empty one
        // means "unrestricted" and clears it.
        if (array_key_exists('web_tool_policy', $data)) {
            $policy = $data['web_tool_policy'];
            $data['web_tool_policy'] = $this->secrets->webToolPolicy(
                $system->web_tool_policy,
                is_array($policy) ? $policy : null,
            );
        }
    }

    /**
     * Shape the validated data for the system's role. A voice system's blank
     * model is stored empty (the column is not nullable) so the provider's
     * default applies, a blank token limit keeps the column default, and an
     * audio system's default voice lives in `config.voice`, leaving the
     * rest of `config` as submitted (or as stored, when none was submitted).
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>|null  $storedConfig
     */
    private function applyRoleFields(array &$data, AiSystemRole $role, bool $settingModel, ?array $storedConfig = null): void
    {
        $defaultVoice = $data['default_voice'] ?? null;
        unset($data['default_voice']);

        if ($role === AiSystemRole::Chat) {
            return;
        }

        if ($settingModel) {
            $data['model'] = $data['model'] ?? '';
        }

        if (($data['max_tokens'] ?? null) === null) {
            unset($data['max_tokens']);
        }

        if ($role === AiSystemRole::Audio) {
            $config = array_key_exists('config', $data) ? $data['config'] : $storedConfig;
            $config = is_array($config) ? $config : [];
            unset($config['voice']);

            if (filled($defaultVoice)) {
                $config['voice'] = $defaultVoice;
            }

            $data['config'] = $config === [] ? null : $config;
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
