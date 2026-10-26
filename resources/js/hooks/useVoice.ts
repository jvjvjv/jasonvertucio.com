import { usePage } from "@inertiajs/react";
import { useCallback, useEffect, useSyncExternalStore } from "react";

import type { SharedProps, VoiceConfig } from "@/types";

import { api } from "@/api";

type VoiceReason = "not_configured" | "inactive" | "unreachable";

interface FeatureStatus {
    available: boolean;
    reason: VoiceReason | null;
}

interface VoiceStatus {
    dictation: FeatureStatus;
    speech: FeatureStatus;
}

export interface UseVoiceResult {
    config: VoiceConfig;
    /** False until the status check answers, and whenever it says no. */
    dictationAvailable: boolean;
    speechAvailable: boolean;
    /** Why dictation is unavailable, for a disabled control's tooltip. */
    dictationReason: string | null;
    speechReason: string | null;
    /** Checks again; call after a voice request fails. */
    refresh: () => void;
}

const REASONS: { [reason in VoiceReason]: string } = {
    not_configured: "Voice is not set up: no default system is assigned.",
    inactive: "The voice system is inactive.",
    unreachable: "The speech server cannot be reached.",
};

const CHECKING = "Checking whether voice is available…";

/**
 * One status per page, shared by every control that asks: a transcript can
 * hold dozens of replies, and each must not run its own check.
 */
let status: VoiceStatus | null = null;
let inflight: Promise<void> | null = null;
let checkedUrl: string | null = null;
const listeners = new Set<() => void>();

function publish(next: VoiceStatus): void {
    status = next;
    listeners.forEach((listener) => {
        listener();
    });
}

function check(statusUrl: string, force = false): void {
    if (inflight !== null || (!force && checkedUrl === statusUrl)) {
        return;
    }

    checkedUrl = statusUrl;
    inflight = api
        .get<VoiceStatus>(statusUrl)
        .then(publish)
        .catch(() => {
            publish({
                dictation: { available: false, reason: "unreachable" },
                speech: { available: false, reason: "unreachable" },
            });
        })
        .finally(() => {
            inflight = null;
        });
}

function subscribe(listener: () => void): () => void {
    listeners.add(listener);

    return () => {
        listeners.delete(listener);
    };
}

function describe(feature: FeatureStatus | undefined): string | null {
    if (feature === undefined) {
        return CHECKING;
    }

    return feature.available
        ? null
        : REASONS[feature.reason ?? "not_configured"];
}

/**
 * Voice for the current user, or null when they do not hold `use-voice`, in
 * which case no voice control should render at all.
 */
export default function useVoice(): UseVoiceResult | null {
    const config = usePage<SharedProps>().props.voice ?? null;
    const current = useSyncExternalStore(subscribe, () => status);
    const statusUrl = config?.statusUrl ?? null;

    useEffect(() => {
        if (statusUrl !== null) {
            check(statusUrl);
        }
    }, [statusUrl]);

    const refresh = useCallback(() => {
        if (statusUrl !== null) {
            check(statusUrl, true);
        }
    }, [statusUrl]);

    if (config === null) {
        return null;
    }

    return {
        config,
        dictationAvailable: current?.dictation.available ?? false,
        speechAvailable: current?.speech.available ?? false,
        dictationReason: describe(current?.dictation),
        speechReason: describe(current?.speech),
        refresh,
    };
}
