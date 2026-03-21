<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAiFeatureMemoryRequest;
use App\Http\Requests\Admin\UpdateAiFeatureMemoryRequest;
use App\Jobs\ProcessAiMemoryJob;
use App\Models\AiFeatureMemory;
use App\Services\AiMemoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiMemoryController extends Controller
{
    /**
     * Display all AI feature memories.
     */
    public function index(Request $request): View
    {
        $query = AiFeatureMemory::query()
            ->with('sourceConversation')
            ->orderByDesc('is_active')
            ->orderByDesc('confidence')
            ->orderByDesc('times_reinforced');

        if ($request->filled('feature')) {
            $query->forFeature($request->input('feature'));
        }

        if ($request->filled('category')) {
            $query->byCategory($request->input('category'));
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->input('status') === 'active');
        }

        $memories = $query->paginate(50);

        $features = AiFeatureMemory::query()
            ->select('feature')
            ->distinct()
            ->pluck('feature');

        return view('admin.ai.memories.index', compact('memories', 'features'));
    }

    /**
     * Show the form for creating a new memory entry.
     */
    public function create(): View
    {
        return view('admin.ai.memories.create');
    }

    /**
     * Store a manually created memory entry.
     */
    public function store(StoreAiFeatureMemoryRequest $request): RedirectResponse
    {
        AiFeatureMemory::create($request->validated());

        return redirect()->route('admin.ai.memories.index')
            ->with('success', 'Memory entry created successfully.');
    }

    /**
     * Show the form for editing a memory entry.
     */
    public function edit(AiFeatureMemory $memory): View
    {
        return view('admin.ai.memories.edit', compact('memory'));
    }

    /**
     * Update the specified memory entry.
     */
    public function update(UpdateAiFeatureMemoryRequest $request, AiFeatureMemory $memory): RedirectResponse
    {
        $memory->update($request->validated());

        return redirect()->route('admin.ai.memories.index')
            ->with('success', 'Memory entry updated successfully.');
    }

    /**
     * Delete the specified memory entry.
     */
    public function destroy(AiFeatureMemory $memory): RedirectResponse
    {
        $memory->delete();

        return redirect()->route('admin.ai.memories.index')
            ->with('success', 'Memory entry deleted.');
    }

    /**
     * Rebuild all memories for a feature from scratch.
     */
    public function rebuild(string $feature, AiMemoryService $memoryService): RedirectResponse
    {
        $memoryService->rebuildMemories($feature);

        return redirect()->route('admin.ai.memories.index', ['feature' => $feature])
            ->with('success', "Memories for \"{$feature}\" have been rebuilt.");
    }
}
