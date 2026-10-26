import { useCallback, useEffect, useRef, useState } from "react";

import { api, ApiError, apiErrorMessage } from "@/api";

export type DictationState = "idle" | "recording" | "transcribing";

interface UseDictationOptions {
    transcribeUrl: string;
    maxSeconds: number;
    onTranscript: (text: string) => void;
    /** Called when the server says voice is unavailable, so status can be rechecked. */
    onUnavailable: () => void;
}

export interface UseDictationResult {
    state: DictationState;
    elapsedSeconds: number;
    /** The last problem to show the user, cleared by the next recording. */
    message: string | null;
    start: () => void;
    /** Stops recording and transcribes what was said. */
    stop: () => void;
    /** Stops recording and throws the recording away. */
    cancel: () => void;
}

/**
 * Records from the microphone and turns the recording into text. The
 * recording lives only in memory until it is uploaded, and the transcript is
 * handed to `onTranscript`; nothing here sends a chat message.
 */
export default function useDictation({
    transcribeUrl,
    maxSeconds,
    onTranscript,
    onUnavailable,
}: UseDictationOptions): UseDictationResult {
    const [state, setState] = useState<DictationState>("idle");
    const [elapsedSeconds, setElapsedSeconds] = useState(0);
    const [message, setMessage] = useState<string | null>(null);

    const recorderRef = useRef<MediaRecorder | null>(null);
    const cancelledRef = useRef(false);
    const startedAtRef = useRef(0);
    const callbacksRef = useRef({ onTranscript, onUnavailable });

    useEffect(() => {
        callbacksRef.current = { onTranscript, onUnavailable };
    });

    const transcribe = useCallback(
        async (blob: Blob) => {
            // MediaRecorder labels its output with a codec suffix
            // (`audio/webm;codecs=opus`); the server and the speech gateway
            // match on the bare type.
            const type = (blob.type.split(";")[0] || "audio/webm").trim();
            const extension = type.split("/")[1]?.replace("x-", "") ?? "webm";
            const form = new FormData();
            form.append(
                "audio",
                new File([blob], `dictation.${extension}`, { type }),
            );

            setState("transcribing");

            try {
                const { text } = await api.upload<{ text: string }>(
                    transcribeUrl,
                    form,
                );

                if (text.trim() === "") {
                    setMessage("Nothing was heard. Try again.");
                } else {
                    callbacksRef.current.onTranscript(text.trim());
                }
            } catch (error) {
                if (error instanceof ApiError && error.status === 503) {
                    callbacksRef.current.onUnavailable();
                }

                setMessage(
                    apiErrorMessage(
                        error,
                        "Voice is unavailable.",
                        "Voice is unavailable.",
                    ),
                );
            } finally {
                setState("idle");
            }
        },
        [transcribeUrl],
    );

    const finish = useCallback((discard: boolean) => {
        const recorder = recorderRef.current;

        if (recorder === null || recorder.state === "inactive") {
            return;
        }

        cancelledRef.current = discard;
        recorder.stop();
    }, []);

    const stop = useCallback(() => {
        finish(false);
    }, [finish]);

    const cancel = useCallback(() => {
        finish(true);
    }, [finish]);

    const start = useCallback(() => {
        if (recorderRef.current !== null) {
            return;
        }

        setMessage(null);

        // Absent on insecure origins and in older browsers, whatever the
        // DOM typings say.
        if (!("MediaRecorder" in window) || !("mediaDevices" in navigator)) {
            setMessage("This browser cannot record audio.");

            return;
        }

        navigator.mediaDevices
            .getUserMedia({ audio: true })
            .then((stream) => {
                const recorder = new MediaRecorder(stream);
                const chunks: Blob[] = [];

                recorder.ondataavailable = (event) => {
                    if (event.data.size > 0) {
                        chunks.push(event.data);
                    }
                };
                recorder.onstop = () => {
                    stream.getTracks().forEach((track) => {
                        track.stop();
                    });
                    recorderRef.current = null;

                    if (cancelledRef.current || chunks.length === 0) {
                        setState("idle");

                        return;
                    }

                    void transcribe(
                        new Blob(chunks, { type: recorder.mimeType }),
                    );
                };

                recorderRef.current = recorder;
                cancelledRef.current = false;
                startedAtRef.current = Date.now();
                setElapsedSeconds(0);
                setState("recording");
                recorder.start();
            })
            .catch((error: unknown) => {
                const denied =
                    error instanceof DOMException &&
                    (error.name === "NotAllowedError" ||
                        error.name === "SecurityError");

                setMessage(
                    denied
                        ? "Microphone access was denied."
                        : "No microphone could be opened.",
                );
            });
    }, [transcribe]);

    useEffect(() => {
        if (state !== "recording") {
            return;
        }

        const timer = window.setInterval(() => {
            const seconds = Math.floor(
                (Date.now() - startedAtRef.current) / 1000,
            );
            setElapsedSeconds(seconds);

            if (seconds >= maxSeconds) {
                stop();
            }
        }, 250);

        return () => {
            window.clearInterval(timer);
        };
    }, [state, maxSeconds, stop]);

    // Leaving the page mid-recording releases the microphone and uploads nothing.
    useEffect(() => cancel, [cancel]);

    return { state, elapsedSeconds, message, start, stop, cancel };
}
