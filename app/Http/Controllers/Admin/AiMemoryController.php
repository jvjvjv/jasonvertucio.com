<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAiFeatureMemoryRequest;
use App\Http\Requests\Admin\UpdateAiFeatureMemoryRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Jvjvjv\CodeTalker\Models\AiFeatureMemory;
use Jvjvjv\CodeTalker\Services\Management\AiMemoryManager;

/**
 * code-talker 0.11.0 removed the package's admin `AiMemoryController` — this
 * is a from-scratch host controller built on `AiMemoryManager`, reproducing
 * the removed controller's behavior.
 */
class AiMemoryController extends Controller
{
    public function __construct(private AiMemoryManager $memories)
    {
    }

    /**
     * Display all AI feature memories.
     */
    public function index(Request $request): InertiaResponse
    {
        $filters = $request->only(['feature', 'category', 'status']);

        return Inertia::render('ai/memories/Index', [
            'memories' => $this->memories->paginate($filters),
            'features' => $this->memories->features(),
            'filters' => $filters,
        ]);
    }

    /**
     * Show the form for creating a new memory entry.
     */
    public function create(): InertiaResponse
    {
        return Inertia::render('ai/memories/Create');
    }

    /**
     * Store a manually created memory entry.
     */
    public function store(StoreAiFeatureMemoryRequest $request): RedirectResponse
    {
        $this->memories->create($request->validated());

        return redirect()->route('admin.ai.memories.index')
            ->with('success', 'Memory entry created successfully.');
    }

    /**
     * Show the form for editing a memory entry.
     */
    public function edit(AiFeatureMemory $memory): InertiaResponse
    {
        return Inertia::render('ai/memories/Edit', [
            'memory' => $memory,
        ]);
    }

    /**
     * Update the specified memory entry.
     */
    public function update(UpdateAiFeatureMemoryRequest $request, AiFeatureMemory $memory): RedirectResponse
    {
        $this->memories->update($memory, $request->validated());

        return redirect()->route('admin.ai.memories.index')
            ->with('success', 'Memory entry updated successfully.');
    }

    /**
     * Delete the specified memory entry.
     */
    public function destroy(AiFeatureMemory $memory): RedirectResponse
    {
        $this->memories->delete($memory);

        return redirect()->route('admin.ai.memories.index')
            ->with('success', 'Memory entry deleted.');
    }

    /**
     * Rebuild all memories for a feature from scratch.
     */
    public function rebuild(string $feature): RedirectResponse
    {
        $this->memories->rebuild($feature);

        return redirect()->route('admin.ai.memories.index', ['feature' => $feature])
            ->with('success', "Memories for \"{$feature}\" have been rebuilt.");
    }
}
