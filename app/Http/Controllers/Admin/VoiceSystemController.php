<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AiSystemPresenter;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Jvjvjv\CodeTalker\Enums\AiSystemRole;
use Jvjvjv\CodeTalker\Models\AiSystem;
use Jvjvjv\CodeTalker\Models\AiSystemFeatureDefault;

/**
 * The pages for speech-to-text and text-to-speech systems. They are AiSystem
 * records like chat systems, and their forms save through AiSystemController
 * so validation stays in one place; only the listing and the forms differ.
 */
class VoiceSystemController extends Controller
{
    private const array VOICE_FEATURES = ['voice:dictation', 'voice:speech'];

    public function __construct(private AiSystemPresenter $presenter) {}

    public function index(): InertiaResponse
    {
        $systems = AiSystem::query()
            ->whereIn('role', [AiSystemRole::Audio->value, AiSystemRole::Transcription->value])
            ->with('featureDefaults')
            ->orderBy('name')
            ->get();

        return Inertia::render('ai/voice-systems/Index', [
            'systems' => $systems->map(function (AiSystem $system): array {
                $system->feature_defaults_list = $system->featureDefaults->pluck('feature')->toArray();

                return $this->presenter->present($system);
            })->values(),
        ]);
    }

    public function create(): InertiaResponse
    {
        return Inertia::render('ai/voice-systems/Create', [
            'existingDefaults' => $this->takenDefaults(),
        ]);
    }

    public function edit(AiSystem $aiSystem): InertiaResponse|RedirectResponse
    {
        if (! AiSystemController::isVoice($aiSystem)) {
            return redirect()->route('admin.ai.systems.edit', $aiSystem);
        }

        $aiSystem->load('featureDefaults');
        $aiSystem->feature_defaults_list = $aiSystem->featureDefaults->pluck('feature')->toArray();

        return Inertia::render('ai/voice-systems/Edit', [
            'aiSystem' => $this->presenter->present($aiSystem),
            'existingDefaults' => $this->takenDefaults($aiSystem),
            'pendingFirstEdit' => $aiSystem->duplicated_at !== null,
        ]);
    }

    /**
     * Voice defaults already held by another system.
     *
     * @return array<int, string>
     */
    private function takenDefaults(?AiSystem $except = null): array
    {
        return AiSystemFeatureDefault::query()
            ->whereIn('feature', self::VOICE_FEATURES)
            ->when($except !== null, fn ($query) => $query->where('ai_system_id', '!=', $except->id))
            ->pluck('feature')
            ->all();
    }
}
