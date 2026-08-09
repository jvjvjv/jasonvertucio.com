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
            let sawPageReloadEvent = false;
            let sawAnyStreamData = false;
            let streamErrorReason: ChatStreamErrorReason | undefined;

            const appendToBlocks = (
                type: MessageBlock["type"],
                delta: string,
            ): void => {
                const last = liveBlocks[liveBlocks.length - 1] as
                    | MessageBlock
                    | undefined;
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
            const persistLiveBlocks = (): void => {
                if (liveBlocks.length === 0) return;

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
                            created_at: new Date().toISOString(),
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

                    sawAnyStreamData = true;

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
                        setStreamingToolPanels((prev) => [
                            ...prev,
                            {
                                pretext: event.text,
                                tools: event.tools,
                            },
                        ]);
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

                // Only a genuine client-side interruption (browser tab
                // closed, fetch aborted, page reload) is benign. Backend
                // `type: "error"` events always carry a `reason` code and
                // are real failures — e.g. max_stream_duration — even
                // though their message text may also contain the word
                // "aborted".
                const isBenignStreamReadInterruption =
                    clientInitiatedAbort ||
                    (streamErrorReason === undefined &&
                        (sawPageReloadEvent || sawAnyStreamData) &&
                        /failed to read from stream|abort|aborted/i.test(
                            message,
                        ));

                if (!isBenignStreamReadInterruption) {
                    setError(message);
                }

                persistLiveBlocks();
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
