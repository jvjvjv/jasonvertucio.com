<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAiSystemPromptRequest;
use App\Http\Requests\Admin\UpdateAiSystemPromptRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Jvjvjv\CodeTalker\Models\AiSystemPrompt;
use Jvjvjv\CodeTalker\Services\Management\AiSystemPromptManager;

/**
 * code-talker 0.11.0 removed the package's admin `AiSystemPromptController` —
 * this is a from-scratch host controller built on `AiSystemPromptManager`,
 * reproducing the removed controller's behavior.
 */
class AiSystemPromptController extends Controller
{
    public function __construct(private AiSystemPromptManager $prompts)
    {
    }

    /**
     * Display all system prompts.
     */
    public function index(): InertiaResponse
    {
        return Inertia::render('ai/system-prompts/Index', [
            'prompts' => $this->prompts->list(),
        ]);
    }

    /**
     * Show the form for creating a new system prompt.
     */
    public function create(): InertiaResponse
    {
        return Inertia::render('ai/system-prompts/Create');
    }

    /**
     * Store a new system prompt.
     */
    public function store(StoreAiSystemPromptRequest $request): RedirectResponse
    {
        $prompt = $this->prompts->create($request->validated());

        return redirect()->route('admin.ai.system-prompts.index')
            ->with('success', "System prompt \"{$prompt->title}\" created successfully.");
    }

    /**
     * Show the form for editing a system prompt.
     */
    public function edit(AiSystemPrompt $aiSystemPrompt): InertiaResponse
    {
        return Inertia::render('ai/system-prompts/Edit', [
            'prompt' => $aiSystemPrompt->loadCount('aiSystems'),
        ]);
    }

    /**
     * Update a system prompt.
     */
    public function update(UpdateAiSystemPromptRequest $request, AiSystemPrompt $aiSystemPrompt): RedirectResponse
    {
        $this->prompts->update($aiSystemPrompt, $request->validated());

        return redirect()->route('admin.ai.system-prompts.index')
            ->with('success', "System prompt \"{$aiSystemPrompt->title}\" updated successfully.");
    }

    /**
     * Delete a system prompt and null out references on any AI systems.
     */
    public function destroy(AiSystemPrompt $aiSystemPrompt): RedirectResponse
    {
        $title = $aiSystemPrompt->title;
        $systemCount = $this->prompts->delete($aiSystemPrompt);

        $message = $systemCount > 0
            ? "System prompt \"{$title}\" deleted. {$systemCount} AI system(s) no longer have a prompt assigned."
            : "System prompt \"{$title}\" deleted successfully.";

        return redirect()->route('admin.ai.system-prompts.index')
            ->with('success', $message);
    }

    /**
     * Update a system prompt via JSON (used by the inline Edit Prompt modal).
     */
    public function apiUpdate(UpdateAiSystemPromptRequest $request, AiSystemPrompt $aiSystemPrompt): JsonResponse
    {
        $this->prompts->update($aiSystemPrompt, $request->validated());

        return response()->json(['prompt' => $aiSystemPrompt->fresh()]);
    }
}
