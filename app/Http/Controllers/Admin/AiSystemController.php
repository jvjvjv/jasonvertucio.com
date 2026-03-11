<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAiSystemRequest;
use App\Http\Requests\UpdateAiSystemRequest;
use App\Models\AiInteractionLog;
use App\Models\AiSystem;
use App\Models\AiSystemFeatureDefault;
use App\Services\ClaudeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiSystemController extends Controller
{
    /**
     * Display a list of all AI systems.
     */
    public function index(): View
    {
        $systems = AiSystem::withCount('interactionLogs')
            ->with('featureDefaults')
            ->orderBy('name')
            ->get();

        return view('admin.ai.systems.index', compact('systems'));
    }

    /**
     * Show the form for creating a new AI system.
     */
    public function create(): View
    {
        $existingDefaults = AiSystemFeatureDefault::pluck('feature')->toArray();

        return view('admin.ai.systems.create', compact('existingDefaults'));
    }

    /**
     * Store a newly created AI system.
     */
    public function store(StoreAiSystemRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $featureDefaults = $data['feature_defaults'] ?? [];
        unset($data['feature_defaults']);

        if (isset($data['config'])) {
            $data['config'] = json_decode($data['config'], true);
        }

        $system = AiSystem::create($data);

        $this->syncFeatureDefaults($system, $featureDefaults);

        return redirect()->route('admin.ai.systems.index')
            ->with('success', "AI system \"{$system->name}\" created successfully.");
    }

    /**
     * Show the form for editing an AI system.
     */
    public function edit(AiSystem $aiSystem): View
    {
        $aiSystem->load('featureDefaults');
        $existingDefaults = AiSystemFeatureDefault::where('ai_system_id', '!=', $aiSystem->id)
            ->pluck('feature')
            ->toArray();

        return view('admin.ai.systems.edit', compact('aiSystem', 'existingDefaults'));
    }

    /**
     * Update the specified AI system.
     */
    public function update(UpdateAiSystemRequest $request, AiSystem $aiSystem): RedirectResponse
    {
        $data = $request->validated();
        $featureDefaults = $data['feature_defaults'] ?? [];
        unset($data['feature_defaults']);

        if (isset($data['config'])) {
            $data['config'] = json_decode($data['config'], true);
        }

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
    public function logs(AiSystem $aiSystem): View
    {
        $logs = AiInteractionLog::where('ai_system_id', $aiSystem->id)
            ->with('user')
            ->orderByDesc('created_at')
            ->paginate(50);

        return view('admin.ai.systems.logs', compact('aiSystem', 'logs'));
    }

    /**
     * Fetch available models from a provider's API.
     */
    public function fetchModels(Request $request): JsonResponse
    {
        $request->validate([
            'provider' => ['required', 'string', 'in:anthropic'],
            'api_key' => ['required', 'string'],
        ]);

        try {
            if ($request->input('provider') === 'anthropic') {
                $client = new ClaudeService(
                    apiKey: $request->input('api_key'),
                );
                $models = $client->listModels();

                $formatted = collect($models)->map(fn (array $m) => [
                    'id' => $m['id'],
                    'name' => $m['display_name'] ?? $m['id'],
                ])->sortBy('name')->values()->toArray();

                return response()->json(['models' => $formatted]);
            }

            return response()->json(['models' => [], 'message' => 'Provider not yet supported.'], 422);
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
}
