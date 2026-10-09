import { useEffect, useRef, useState } from "react";

import type { ModelStatus } from "@/components/ChatInterface";

import { api } from "@/api";

export interface UseModelStatusResult {
    modelStatus: ModelStatus | null;
    isCheckingModelStatus: boolean;
    isWarmingModel: boolean;
    loadingMessage: string;
    /**
     * Exposed so `useChatStream` can also surface transient "waiting for
     * model response" text during an active turn — both concerns render
     * into the same status banner.
     */
    setLoadingMessage: (message: string) => void;
}

/**
 * Checks model readiness on mount via `statusUrl` and triggers a warmup
 * request against `warmupUrl` if the model isn't loaded yet.
 */
export default function useModelStatus(
    statusUrl: string,
    warmupUrl: string,
    onModelStatusChange?: (status: ModelStatus | null) => void,
): UseModelStatusResult {
    const [modelStatus, setModelStatus] = useState<ModelStatus | null>(null);
    const [isCheckingModelStatus, setIsCheckingModelStatus] = useState(false);
    const [isWarmingModel, setIsWarmingModel] = useState(false);
    const [loadingMessage, setLoadingMessage] = useState("");

    const onModelStatusChangeRef = useRef(onModelStatusChange);
    useEffect(() => {
        onModelStatusChangeRef.current = onModelStatusChange;
    });

    const updateModelStatus = (status: ModelStatus | null): void => {
        setModelStatus(status);
        onModelStatusChangeRef.current?.(status);
    };

    const setUnavailableStatus = (message: string): void => {
        setModelStatus((current) => {
            const next: ModelStatus = {
                state: "unavailable",
                provider: current?.provider ?? "unknown",
                model: current?.model ?? "",
                message,
                checked_at: new Date().toISOString(),
            };
            onModelStatusChangeRef.current?.(next);
            return next;
        });
    };

    // On mount: check status and auto-warm if needed
    useEffect(() => {
        let mounted = true;

        const prepare = async (): Promise<void> => {
            setIsCheckingModelStatus(true);

            let status: ModelStatus | null = null;
            try {
                const payload = await api.get<{ status?: ModelStatus }>(
                    statusUrl,
                );
                status = payload.status ?? null;
                if (status) updateModelStatus(status);
            } catch {
                setUnavailableStatus("Provider is unavailable.");
            } finally {
                setIsCheckingModelStatus(false);
            }

            if (!mounted || status?.state !== "not_loaded") return;

            setIsWarmingModel(true);
            setLoadingMessage("Loading model. This can take a little while...");

            try {
                const wp = await api.post<{ status?: ModelStatus }>(warmupUrl);
                if (wp.status) updateModelStatus(wp.status);
            } catch {
                // Reported through the status banner like the check above.
                // The state stays `not_loaded` rather than `unavailable`: a
                // warm-up that timed out says nothing about the provider, and
                // `unavailable` would lock the composer.
                updateModelStatus({
                    ...status,
                    message:
                        "The model could not be warmed up. It may still load when you send a message.",
                });
            } finally {
                setIsWarmingModel(false);
                setLoadingMessage("");
            }
        };

        void prepare();
        return () => {
            mounted = false;
        };
    }, [statusUrl, warmupUrl]);

    return {
        modelStatus,
        isCheckingModelStatus,
        isWarmingModel,
        loadingMessage,
        setLoadingMessage,
    };
}
