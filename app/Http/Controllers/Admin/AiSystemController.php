<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Enums\AiProvider;
use App\Http\Requests\StoreAiSystemRequest;
use App\Http\Requests\UpdateAiSystemRequest;
use App\Models\AiInteractionLog;
use App\Models\AiSystem;
use App\Models\AiSystemFeatureDefault;
use App\Services\ClaudeService;
use App\Services\GeminiService;
use App\Services\GrokService;
use App\Services\OpenAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AiSystemController extends Controller
{
    /**
     * Display a list of all AI systems.
     */
    public function index(): InertiaResponse
    {
        $systems = AiSystem::withCount('interactionLogs')
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

        $this->decodeJsonFields($data, ['config', 'credentials', 'pricing_profile']);

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
        $aiSystem->feature_defaults_list = $aiSystem->featureDefaults->pluck('feature')->toArray();

        $existingDefaults = AiSystemFeatureDefault::where('ai_system_id', '!=', $aiSystem->id)
            ->pluck('feature')
            ->toArray();

        return Inertia::render('ai/systems/Edit', [
            'aiSystem' => $aiSystem,
            'existingDefaults' => $existingDefaults,
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

        $this->decodeJsonFields($data, ['config', 'credentials', 'pricing_profile']);

        $aiSystem->update($data);

        $this->syncFeatureDefaults($aiSystem, $featureDefaults);

        return redirect()->route('admin.ai.systems.index')
            ->with('success', "AI system \"{$aiSystem->name}\" updated successfully.");
    }

    /**
     * Remove the specified AI system.
     */
    public function destroy(AiSystem $aiSystem): RedirectResponse
    {
        $name = $aiSystem->name;
        $aiSystem->delete();

        return redirect()->route('admin.ai.systems.index')
            ->with('success', "AI system \"{$name}\" deleted successfully.");
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

            $client = match ($provider) {
                AiProvider::Anthropic => new ClaudeService(
                    apiKey: (string) ($request->input('api_key') ?? ''),
                    baseUrl: $request->string('base_url')->toString() ?: null,
                ),
                AiProvider::OpenAI, AiProvider::OpenAICompatible => new OpenAiService(
                    apiKey: (string) ($request->input('api_key') ?? ''),
                    baseUrl: $request->string('base_url')->toString() ?: null,
                ),
                AiProvider::Gemini => new GeminiService(
                    apiKey: (string) ($request->input('api_key') ?? ''),
                    baseUrl: $request->string('base_url')->toString() ?: null,
                ),
                AiProvider::Grok => new GrokService(
                    apiKey: (string) ($request->input('api_key') ?? ''),
                    baseUrl: $request->string('base_url')->toString() ?: null,
                ),
            };

            $models = $client->listModels();

            $formatted = collect($models)->map(fn(array $m) => [
                'id' => $m['id'],
                'name' => $m['display_name'] ?? $m['id'],
            ])->sortBy('name')->values()->toArray();

            return response()->json(['models' => $formatted]);

        } catch (\Exception $e) {
            return response()->json(['models' => [], 'error' => 'Failed to fetch models: ' . $e->getMessage()], 422);
        }
    }

    /**
     * Duplicate an existing AI system.
     */
    public function duplicate(AiSystem $aiSystem): RedirectResponse
    {
        $clone = $aiSystem->replicate(['id']);
        $clone->name = $aiSystem->name . ' (copy)';
        $clone->save();

        return redirect()->route('admin.ai.systems.edit', $clone)
            ->with('success', "AI system duplicated. Update the name and settings as needed.");
    }

    /**
     * Sync feature defaults for an AI system.
     *
     * @param array<int, string> $features
     */
    private function syncFeatureDefaults(AiSystem $system, array $features): void
    {
        // Remove existing defaults for this system
        AiSystemFeatureDefault::where('ai_system_id', $system->id)->delete();

        // Remove any existing defaults for these features (from other systems)
        if (!empty($features)) {
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
     * @param array<string, mixed> $data
     * @param array<int, string> $fields
     */
    private function decodeJsonFields(array &$data, array $fields): void {
        foreach ($fields as $field) {
            if (!array_key_exists($field, $data) || $data[$field] === null || $data[$field] === '') {
                continue;
            }

            if (is_string($data[$field])) {
                $data[$field] = json_decode($data[$field], true);
            }
        }
    }
}
