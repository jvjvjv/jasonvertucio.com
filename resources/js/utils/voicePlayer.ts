import { useSyncExternalStore } from "react";

import speechBlocks from "./speechBlocks";

import type { VoiceConfig } from "@/types";

/**
 * Plays generated speech, one clip on the whole page at a time. Starting a new
 * reading stops the current one, so two replies can never talk over each
 * other.
 *
 * One `Audio` element is reused for everything: Safari only lets a page play
 * sound from an element that was first played during a user gesture, so
 * `speak()` (always called from a click) unlocks it synchronously before the
 * first clip has even been fetched.
 */

/** A tiny silent WAV, played synchronously to unlock the element. */
const SILENCE =
    "data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQAAAAA=";

export class VoiceUnavailableError extends Error {}

interface Reading {
    owner: string;
    abort: AbortController;
}

let audio: HTMLAudioElement | null = null;
let current: Reading | null = null;
let finishClip: (() => void) | null = null;
const listeners = new Set<() => void>();

function element(): HTMLAudioElement {
    audio ??= new Audio();

    return audio;
}

function setCurrent(reading: Reading | null): void {
    current = reading;
    listeners.forEach((listener) => {
        listener();
    });
}

function csrfToken(): string {
    return (
        document
            .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.getAttribute("content") ?? ""
    );
}

/**
 * One block's speech, or null when the server has nothing to say for it
 * (a 422: markup only, or still too long), so the reading moves on.
 */
async function fetchClip(
    speechUrl: string,
    text: string,
    signal: AbortSignal,
): Promise<Blob | null> {
    const res = await fetch(speechUrl, {
        method: "POST",
        credentials: "same-origin",
        headers: {
            Accept: "audio/mpeg, application/json",
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": csrfToken(),
        },
        body: JSON.stringify({ text }),
        signal,
    });

    if (res.status === 422) {
        return null;
    }

    if (!res.ok) {
        throw new VoiceUnavailableError("Voice is unavailable.");
    }

    return res.blob();
}

function playClip(blob: Blob): Promise<void> {
    const el = element();
    const url = URL.createObjectURL(blob);

    return new Promise<void>((resolve, reject) => {
        const done = (error?: Error) => {
            el.onended = null;
            el.onerror = null;
            finishClip = null;
            URL.revokeObjectURL(url);
            if (error) {
                reject(error);
            } else {
                resolve();
            }
        };

        finishClip = () => {
            done();
        };
        el.onended = () => {
            done();
        };
        el.onerror = () => {
            done(new VoiceUnavailableError("The speech could not be played."));
        };
        el.src = url;
        el.play().catch((error: unknown) => {
            done(error instanceof Error ? error : new Error(String(error)));
        });
    });
}

/** Stops whatever is being read, if anything. */
export function stopSpeaking(): void {
    if (current === null) {
        return;
    }

    current.abort.abort();
    element().pause();
    finishClip?.();
    setCurrent(null);
}

/**
 * Reads `markdown` aloud as `owner`, one block per request, fetching the next
 * block while the current one plays. Resolves when the reading ends, is
 * stopped, or is replaced; rejects with `VoiceUnavailableError` when the
 * server fails.
 */
export async function speak(
    owner: string,
    markdown: string,
    voice: Pick<VoiceConfig, "speechUrl" | "maxSpeechChars">,
): Promise<void> {
    stopSpeaking();

    const el = element();
    el.src = SILENCE;
    el.play().catch(() => undefined);

    const reading: Reading = { owner, abort: new AbortController() };
    setCurrent(reading);

    const blocks = speechBlocks(markdown, voice.maxSpeechChars);
    const { signal } = reading.abort;
    // A function rather than a property read: Stop flips it between awaits.
    const stopped = () => signal.aborted;
    const request = (text: string) => {
        const clip = fetchClip(voice.speechUrl, text, signal);
        // A prefetch abandoned by Stop rejects with nobody awaiting it yet.
        clip.catch(() => undefined);

        return clip;
    };

    try {
        let next = blocks.length > 0 ? request(blocks[0]) : null;

        for (let i = 0; next !== null; i++) {
            const clip = await next;
            next = i + 1 < blocks.length ? request(blocks[i + 1]) : null;

            if (stopped()) {
                return;
            }

            if (clip !== null) {
                await playClip(clip);
            }

            if (stopped()) {
                return;
            }
        }
    } catch (error) {
        if (!stopped()) {
            throw error;
        }
    } finally {
        if (current === reading) {
            setCurrent(null);
        }
    }
}

function subscribe(listener: () => void): () => void {
    listeners.add(listener);

    return () => {
        listeners.delete(listener);
    };
}

/** The owner of the reading in progress, or null when nothing is playing. */
export function useSpeakingOwner(): string | null {
    return useSyncExternalStore(subscribe, () => current?.owner ?? null);
}
