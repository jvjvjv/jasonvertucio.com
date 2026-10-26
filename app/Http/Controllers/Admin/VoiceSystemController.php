<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VoiceSystem;
use App\Services\AiSystemPresenter;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Jvjvjv\CodeTalker\Models\AiSystemFeatureDefault;

/**
 * The pages for speech-to-text and text-to-speech systems. They are AiSystem
 * rows like chat systems, read through the VoiceSystem scope, so a chat
 * system's id is not found here. Their forms save through AiSystemController
 * so validation stays in one place; only the listing and the forms differ.
 */
class VoiceSystemController extends Controller
{
    private const array VOICE_FEATURES = ['voice:dictation', 'voice:speech'];

    public function __construct(private AiSystemPresenter $presenter) {}

    public function index(): InertiaResponse
    {
        $systems = VoiceSystem::query()
            ->with('featureDefaults')
            ->orderBy('name')
            ->get();

        return Inertia::render('ai/voice-systems/Index', [
            'systems' => $systems->map(function (VoiceSystem $system): array {
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

    public function edit(VoiceSystem $voiceSystem): InertiaResponse
    {
        $voiceSystem->load('featureDefaults');
        $voiceSystem->feature_defaults_list = $voiceSystem->featureDefaults->pluck('feature')->toArray();

        return Inertia::render('ai/voice-systems/Edit', [
            'aiSystem' => $this->presenter->present($voiceSystem),
            'existingDefaults' => $this->takenDefaults($voiceSystem),
            'pendingFirstEdit' => $voiceSystem->duplicated_at !== null,
        ]);
    }

    /**
     * Voice defaults already held by another system.
     *
     * @return array<int, string>
     */
    private function takenDefaults(?VoiceSystem $except = null): array
    {
        return AiSystemFeatureDefault::query()
            ->whereIn('feature', self::VOICE_FEATURES)
            ->when($except !== null, fn ($query) => $query->where('ai_system_id', '!=', $except->id))
            ->pluck('feature')
            ->all();
    }
}
