import { useCallback, useEffect, useRef, useState } from "react";

export interface SessionExpiry {
    isExpired: boolean;
    /** Push the deadline forward by the original session lifetime (call on API activity). */
    extend: () => void;
    /** Flip to expired immediately (call on a definitive 419). */
    markExpired: () => void;
}

/**
 * Tracks a server-issued session deadline client-side. The Laravel session
 * cookie is http-only, so it can't be read directly — the deadline instead
 * comes from a shared Inertia prop, extended locally on successful API
 * activity to mirror Laravel's sliding session lifetime.
 */
export default function useSessionExpiry(
    initialExpiresAt: string,
): SessionExpiry {
    const [deadline, setDeadline] = useState(() =>
        Date.parse(initialExpiresAt),
    );
    const lifetimeMsRef = useRef<number | null>(null);
    const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const [isExpired, setIsExpired] = useState(false);

    const clearTimer = useCallback(() => {
        if (timerRef.current !== null) {
            clearTimeout(timerRef.current);
            timerRef.current = null;
        }
    }, []);

    const markExpired = useCallback(() => {
        clearTimer();
        setIsExpired(true);
    }, [clearTimer]);

    const extend = useCallback(() => {
        const lifetimeMs = lifetimeMsRef.current ?? 0;
        setDeadline(Date.now() + lifetimeMs);
        setIsExpired(false);
    }, []);

    // Establish the initial lifetime (needs "now", so it belongs in an
    // effect rather than during render) and schedule/reschedule the timer
    // whenever the deadline changes.
    useEffect(() => {
        if (lifetimeMsRef.current === null) {
            lifetimeMsRef.current = Math.max(0, deadline - Date.now());
        }

        clearTimer();
        timerRef.current = setTimeout(
            () => {
                setIsExpired(true);
            },
            Math.max(0, deadline - Date.now()),
        );

        return clearTimer;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [deadline]);

    // Background-tab timers can be throttled or paused — recheck on focus.
    useEffect(() => {
        const recheck = (): void => {
            if (!isExpired && Date.now() >= deadline) {
                markExpired();
            }
        };
        document.addEventListener("visibilitychange", recheck);
        window.addEventListener("focus", recheck);
        return () => {
            document.removeEventListener("visibilitychange", recheck);
            window.removeEventListener("focus", recheck);
        };
    }, [isExpired, deadline, markExpired]);

    return { isExpired, extend, markExpired };
}
