import { useCallback, useEffect, useRef, useState } from "react";

import { INTEL_API, isIntelInProgress } from "./types";

import type { RoleIntel } from "./types";

import { api } from "@/api";

const POLL_INTERVAL_MS = 4000;

/**
 * The intel's findings and state, refreshed from the `state` endpoint. While
 * a background run is queued or running (and nothing is streaming here) it
 * polls, and calls `onSettled` once the run leaves that state so the page
 * can pick up the transcript the run wrote.
 */
export default function useIntelState(
    initial: RoleIntel,
    {
        pollWhileInProgress,
        onSettled,
    }: { pollWhileInProgress: boolean; onSettled: () => void },
) {
    const [intel, setIntel] = useState(initial);
    const [seenInitial, setSeenInitial] = useState(initial);
    const onSettledRef = useRef(onSettled);

    // New props from a reload replace whatever polling last fetched.
    if (seenInitial !== initial) {
        setSeenInitial(initial);
        setIntel(initial);
    }

    useEffect(() => {
        onSettledRef.current = onSettled;
    }, [onSettled]);

    const refresh = useCallback(async (): Promise<RoleIntel | null> => {
        try {
            const next = await api.get<RoleIntel>(
                `${INTEL_API}/${initial.id}/state`,
            );
            setIntel(next);
            return next;
        } catch {
            return null;
        }
    }, [initial.id]);

    const inProgress = isIntelInProgress(intel.status);

    useEffect(() => {
        if (!pollWhileInProgress || !inProgress) {
            return;
        }

        const timer = window.setInterval(() => {
            void refresh().then((next) => {
                if (next !== null && !isIntelInProgress(next.status)) {
                    onSettledRef.current();
                }
            });
        }, POLL_INTERVAL_MS);

        return () => {
            window.clearInterval(timer);
        };
    }, [pollWhileInProgress, inProgress, refresh]);

    return { intel, setIntel, refresh };
}
