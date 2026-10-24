import { useCallback, useState } from "react";

import { INTEL_API } from "./types";

import type { IntelSystemOption } from "./types";

import { api, apiErrorMessage, networkErrorMessage } from "@/api";

export type IntelStartMode = "background" | "stream";

interface EligibleSystems {
    default: IntelSystemOption | null;
    systems: IntelSystemOption[];
}

export interface StartedIntel {
    role_intel_id: number;
    status: string;
    redirect: string;
}

interface PendingStart {
    applicationId: number;
    mode: IntelStartMode;
    systems: IntelSystemOption[];
}

/**
 * Starts intel for an application. With a `company-intel` default the start
 * is immediate; without one the caller renders {@link IntelSystemDialog}
 * from `chooser`, and the start happens once a system is picked.
 */
export default function useBeginIntel(
    onStarted: (started: StartedIntel, mode: IntelStartMode) => void,
) {
    const [startingId, setStartingId] = useState<number | null>(null);
    const [chooser, setChooser] = useState<PendingStart | null>(null);
    const [error, setError] = useState<string | null>(null);

    const startWith = useCallback(
        async (
            applicationId: number,
            mode: IntelStartMode,
            aiSystemId: number | null,
        ) => {
            setStartingId(applicationId);
            setError(null);
            try {
                const started = await api.post<StartedIntel>(
                    `${INTEL_API}/applications/${applicationId}`,
                    { mode, ai_system_id: aiSystemId },
                );
                setChooser(null);
                onStarted(started, mode);
            } catch (err) {
                setError(
                    apiErrorMessage(
                        err,
                        "Could not start intel.",
                        networkErrorMessage(err),
                    ),
                );
            } finally {
                setStartingId(null);
            }
        },
        [onStarted],
    );

    const begin = useCallback(
        async (applicationId: number, mode: IntelStartMode) => {
            setStartingId(applicationId);
            setError(null);
            try {
                const eligible = await api.get<EligibleSystems>(
                    `${INTEL_API}/eligible-systems`,
                );
                if (eligible.default !== null) {
                    await startWith(applicationId, mode, null);
                    return;
                }
                setChooser({ applicationId, mode, systems: eligible.systems });
            } catch (err) {
                setError(
                    apiErrorMessage(
                        err,
                        "Could not load the AI systems.",
                        networkErrorMessage(err),
                    ),
                );
            }
            setStartingId(null);
        },
        [startWith],
    );

    const choose = useCallback(
        (aiSystemId: number) => {
            if (chooser !== null) {
                void startWith(chooser.applicationId, chooser.mode, aiSystemId);
            }
        },
        [chooser, startWith],
    );

    const closeChooser = useCallback(() => {
        setChooser(null);
        setError(null);
    }, []);

    return {
        begin,
        startingId,
        error,
        clearError: () => {
            setError(null);
        },
        chooser,
        choose,
        closeChooser,
    };
}
