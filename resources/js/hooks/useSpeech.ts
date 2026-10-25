import { useId, useState } from "react";

import type { UseVoiceResult } from "@/hooks/useVoice";

import {
    VoiceUnavailableError,
    speak,
    stopSpeaking,
    useSpeakingOwner,
} from "@/utils/voicePlayer";

export interface UseSpeechResult {
    isSpeaking: boolean;
    /** Starts reading `getText()`, or stops if this control is already reading. */
    toggle: () => void;
    error: string | null;
}

/**
 * Play/stop for one control. Each control has its own owner id, so starting
 * one stops any other and every control knows whether it is the one playing.
 */
export default function useSpeech(
    voice: UseVoiceResult,
    getText: () => string,
): UseSpeechResult {
    const owner = useId();
    const isSpeaking = useSpeakingOwner() === owner;
    const [error, setError] = useState<string | null>(null);

    const toggle = () => {
        if (isSpeaking) {
            stopSpeaking();

            return;
        }

        setError(null);
        speak(owner, getText(), voice.config).catch((reason: unknown) => {
            if (reason instanceof VoiceUnavailableError) {
                voice.refresh();
            }

            setError("Voice is unavailable.");
        });
    };

    return { isSpeaking, toggle, error };
}
