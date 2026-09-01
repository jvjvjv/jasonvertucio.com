import { router } from "@inertiajs/react";
import { useCallback, useEffect, useRef, useState } from "react";

import type { ChatMessage, StreamEvent } from "@/components/ChatInterface";
import type { MessageBlock } from "@/components/ChatMessageBubble";
import type { ToolPanel } from "@/components/ToolsPanel";
import type { ChatStreamErrorReason } from "@/types/code-talker";

import { api } from "@/api";

export interface UseChatStreamOptions {
    chatEndpoint: string;
    initialMessages: ChatMessage[];
    messageText: string;
    onMessageSent: () => void;
    isExpired: boolean;
    shouldAutoStart?: boolean;
    autoStartMessage?: string;
    /** Extra fields merged into the POST body on every send. Stable reference preferred. */
    extraPayload?: { [key: string]: string | null | undefined };
    /** Called for every parsed SSE event, including tool_use_progress and page_reload. */
    onEvent?: (event: StreamEvent) => void;
    /** Called when streaming finishes (success or error). */
    onStreamEnd?: () => void;
    /** Called whenever the message list changes (useful for scanning message content). */
    onMessagesChange?: (messages: ChatMessage[]) => void;
    /** Called immediately when the stream response arrives (before body is read). */
    onStreamResponse?: (response: Response) => void;
    /** Shared with `useModelStatus` so streaming status text renders in the same banner. */
    setLoadingMessage: (message: string) => void;
}

export interface UseChatStreamResult {
    messages: ChatMessage[];
    streamingBlocks: MessageBlock[];
    streamingToolPanels: ToolPanel[];
    isStreaming: boolean;
    error: string;
    sendMessage: (messageOverride?: string) => Promise<void>;
    stopStreaming: () => void;
}

/**
 * Owns message state and the SSE stream lifecycle for a chat turn: sending,
 * parsing streamed reasoning/text/tool-use events, and persisting whatever
 * streamed once the turn ends (cleanly or via error/abort).
 */
export default function useChatStream({
    chatEndpoint,
    initialMessages,
    messageText,
    onMessageSent,
    isExpired,
    shouldAutoStart = false,
    autoStartMessage,
    extraPayload,
    onEvent,
    onStreamEnd,
    onMessagesChange,
    onStreamResponse,
    setLoadingMessage,
}: UseChatStreamOptions): UseChatStreamResult {
    const [messages, setMessages] = useState<ChatMessage[]>(initialMessages);
    const [streamingBlocks, setStreamingBlocks] = useState<MessageBlock[]>([]);
    const [streamingToolPanels, setStreamingToolPanels] = useState<ToolPanel[]>(
        [],
    );
    const [isStreaming, setIsStreaming] = useState(false);
    const [error, setError] = useState("");

    const hasAutoStarted = useRef(false);
    const extraPayloadRef = useRef(extraPayload);
    const onMessagesChangeRef = useRef(onMessagesChange);
    const streamingRafRef = useRef<number | null>(null);
    const abortControllerRef = useRef<AbortController | null>(null);

    // Keep callback refs fresh without mutating during render
    useEffect(() => {
        extraPayloadRef.current = extraPayload;
    });
    useEffect(() => {
        onMessagesChangeRef.current = onMessagesChange;
    });

    // Reset messages when the conversation changes (initialMessages reference changes).
    // React's "setState during render" pattern — React re-renders immediately with new state.
    const [prevInitialMessages, setPrevInitialMessages] =
        useState(initialMessages);
    if (prevInitialMessages !== initialMessages) {
        setPrevInitialMessages(initialMessages);
        setMessages(initialMessages);
    }

    // Notify parent whenever messages change (including on conversation reset above)
    useEffect(() => {
        onMessagesChangeRef.current?.(messages);
    }, [messages]);

    useEffect(() => {
        return () => {
            if (streamingRafRef.current !== null) {
                cancelAnimationFrame(streamingRafRef.current);
                streamingRafRef.current = null;
            }
        };
    }, []);

    // A turn lives and dies with this connection. The server only notices the
    // browser hung up the next time it writes to the socket — which, against a
    // slow local model, can be minutes after the fact — and everything the turn
    // produced up to that point is then discarded rather than persisted. So a
    // reload mid-turn doesn't pause the reply, it loses it outright. Make that a
    // deliberate choice rather than an accident.
    //
    // Only guards a real page unload; an Inertia visit doesn't fire this, and
    // neither does the router.reload() in the error path below (by then the
    // turn is already over).
    useEffect(() => {
        if (!isStreaming) return;

        // preventDefault() is what asks for the prompt; browsers supply their
        // own copy and ignore any message the page tries to set. (The legacy
        // `returnValue = ""` companion is deprecated and no longer needed.)
        const warnBeforeUnload = (event: BeforeUnloadEvent): void => {
            event.preventDefault();
        };

        window.addEventListener("beforeunload", warnBeforeUnload);

        return () => {
            window.removeEventListener("beforeunload", warnBeforeUnload);
        };
    }, [isStreaming]);

    const sendMessage = useCallback(
        async (messageOverride?: string) => {
            const text = messageOverride ?? messageText.trim();

            if (!text && !shouldAutoStart) return;
            if (isStreaming) return;
            if (isExpired) return;

            if (text) {
                setMessages((prev) => {
                    const next = [
                        ...prev,
                        {
                            role: "user" as const,
                            content: text,
                            created_at: new Date().toISOString(),
                        },
                    ];
                    onMessagesChangeRef.current?.(next);
                    return next;
                });
                onMessageSent();
            }

            setIsStreaming(true);
            setStreamingBlocks([]);
            setStreamingToolPanels([]);
            setError("");

            let liveBlocks: MessageBlock[] = [];
            // Mirrors streamingToolPanels state so persistLiveBlocks() (a plain
            // closure, not a render) can read this turn's tool activity
            // synchronously instead of a stale value captured at render time —
            // same reason liveBlocks mirrors streamingBlocks above.
            let liveToolPanels: ToolPanel[] = [];
            let sawPageReloadEvent = false;
            let streamErrorReason: ChatStreamErrorReason | undefined;

            const appendToBlocks = (
                type: MessageBlock["type"],
                delta: string,
            ): void => {
                const last = liveBlocks[liveBlocks.length - 1] as
                    MessageBlock | undefined;
                if (last?.type === type) {
                    liveBlocks = [
                        ...liveBlocks.slice(0, -1),
                        { type, content: last.content + delta },
                    ];
                } else {
                    liveBlocks = [...liveBlocks, { type, content: delta }];
                }

                streamingRafRef.current ??= requestAnimationFrame(() => {
                    streamingRafRef.current = null;
                    setStreamingBlocks([...liveBlocks]);
                });
            };

            // Turns whatever streamed so far into a normal assistant
            // message. Used both when the stream finishes cleanly and
            // when it errors out mid-turn (e.g. a max-duration abort) —
            // reasoning/text that already rendered live shouldn't vanish
            // just because the turn ultimately failed.
            //
            // `incomplete` mirrors the flag the server puts on the same turn
            // (code-talker 0.15.0+), so a reply that stops mid-sentence is
            // marked as interrupted right away rather than only after a
            // reload re-reads it from the transcript.
            const persistLiveBlocks = (incomplete = false): void => {
                if (liveBlocks.length === 0 && liveToolPanels.length === 0) {
                    return;
                }

                const finalText = liveBlocks
                    .filter((b) => b.type === "text")
                    .map((b) => b.content)
                    .join("");

                setMessages((prev) => {
                    const next = [
                        ...prev,
                        {
                            role: "assistant" as const,
                            content: finalText,
                            blocks: liveBlocks,
                            tool_panels:
                                liveToolPanels.length > 0
                                    ? liveToolPanels
                                    : undefined,
                            created_at: new Date().toISOString(),
                            incomplete,
                        },
                    ];
                    onMessagesChangeRef.current?.(next);
                    return next;
                });
            };

            const abortController = new AbortController();
            abortControllerRef.current = abortController;

            try {
                const extra = extraPayloadRef.current ?? {};
                const payload: {
                    [key: string]: string | null | undefined;
                } = {
                    message: text || null,
                    ...extra,
                };

                for await (const jsonStr of api.stream(
                    chatEndpoint,
                    payload,
                    onStreamResponse,
                    true,
                    abortController.signal,
                )) {
                    if (!jsonStr || jsonStr === "[DONE]") continue;

                    let event: StreamEvent;
                    try {
                        event = JSON.parse(jsonStr) as StreamEvent;
                    } catch {
                        continue;
                    }

                    if (event.type === "page_reload") {
                        sawPageReloadEvent = true;
                    }

                    onEvent?.(event);

                    if (
                        event.type === "reasoning_block_delta" &&
                        event.delta.reasoning
                    ) {
                        setLoadingMessage("");
                        appendToBlocks("reasoning", event.delta.reasoning);
                    } else if (event.type === "content_block_delta") {
                        if (event.delta.text) {
                            setLoadingMessage("");
                            appendToBlocks("text", event.delta.text);
                        }
                    } else if (event.type === "tool_use_progress") {
                        if ("output" in event) {
                            // A result frame (usingToolPayloads() only — see
                            // ChatBotController::message()) merges onto the
                            // most recent call frame for this tool that
                            // doesn't have a result yet, rather than adding a
                            // second panel for the same call.
                            const fromEnd = [...liveToolPanels]
                                .reverse()
                                .findIndex(
                                    (p) =>
                                        p.output === undefined &&
                                        p.tools.some((t) =>
                                            event.tools.includes(t),
                                        ),
                                );

                            if (fromEnd === -1) {
                                liveToolPanels = [
                                    ...liveToolPanels,
                                    {
                                        pretext: event.text,
                                        tools: event.tools,
                                        output: event.output,
                                        successful: event.successful,
                                    },
                                ];
                            } else {
                                const index =
                                    liveToolPanels.length - 1 - fromEnd;
                                const next = [...liveToolPanels];
                                next[index] = {
                                    ...next[index],
                                    output: event.output,
                                    successful: event.successful,
                                };
                                liveToolPanels = next;
                            }
                        } else {
                            liveToolPanels = [
                                ...liveToolPanels,
                                {
                                    pretext: event.text,
                                    tools: event.tools,
                                    input: event.input,
                                },
                            ];
                        }
                        setStreamingToolPanels(liveToolPanels);
                        liveBlocks = [];
                        setStreamingBlocks([]);
                    } else if (event.type === "status") {
                        setLoadingMessage(event.message);
                    } else if (event.type === "error") {
                        streamErrorReason = event.reason;
                        throw new Error(event.message);
                    }
                }

                persistLiveBlocks();

                onStreamEnd?.();
            } catch (err) {
                const message =
                    err instanceof Error
                        ? err.message
                        : "Unable to send message right now.";

                // The user clicking Stop (or pressing ESC) aborts the
                // fetch via our own AbortController — always benign,
                // regardless of message text or how much had streamed.
                const clientInitiatedAbort =
                    err instanceof DOMException && err.name === "AbortError";

                // A tool-triggered page_reload can also end the read in a
                // way that looks like an abort; that specific combination
                // stays benign since the reload is an intentional signal,
                // not a failure. Anything else that interrupts the stream —
                // a dropped connection, a backend `type: "error"` event —
                // is a genuine failure: the backend may have generated (and
                // persisted) a reply the browser never received, so it's
                // resynced from the server below rather than silently lost.
                const isBenignPageReloadAbort =
                    streamErrorReason === undefined &&
                    sawPageReloadEvent &&
                    /failed to read from stream|abort|aborted/i.test(message);

                if (!clientInitiatedAbort && !isBenignPageReloadAbort) {
                    setError(message);
                    router.reload({ only: ["messages"] });
                }

                // Reaching the catch at all means the turn didn't run to
                // completion — a Stop, a dropped connection, or a backend
                // error frame. The server flags its own copy the same way, so
                // whatever is kept here is marked to match.
                persistLiveBlocks(true);
            } finally {
                abortControllerRef.current = null;

                if (streamingRafRef.current !== null) {
                    cancelAnimationFrame(streamingRafRef.current);
                    streamingRafRef.current = null;
                }

                setIsStreaming(false);
                setStreamingBlocks([]);
                setStreamingToolPanels([]);
                setLoadingMessage("");
            }
        },
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [
            messageText,
            chatEndpoint,
            isStreaming,
            isExpired,
            shouldAutoStart,
            onEvent,
            onStreamEnd,
        ],
    );

    // Aborts the in-flight turn (Stop button / ESC). The stream's own
    // catch block treats this as benign and still keeps whatever had
    // already rendered live.
    const stopStreaming = useCallback(() => {
        abortControllerRef.current?.abort();
    }, []);

    // Auto-start the conversation on mount
    useEffect(() => {
        if (shouldAutoStart && !hasAutoStarted.current) {
            hasAutoStarted.current = true;
            void sendMessage(autoStartMessage ?? "");
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    return {
        messages,
        streamingBlocks,
        streamingToolPanels,
        isStreaming,
        error,
        sendMessage,
        stopStreaming,
    };
}
