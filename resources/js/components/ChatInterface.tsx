import Box from "@mui/material/Box";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";
import Divider from "@mui/material/Divider";
import { useTheme } from "@mui/material/styles";
import useMediaQuery from "@mui/material/useMediaQuery";
import {
    useCallback,
    useEffect,
    useImperativeHandle,
    useRef,
    useState,
    forwardRef,
} from "react";
import { flushSync } from "react-dom";
import { Virtuoso, type VirtuosoHandle } from "react-virtuoso";

import type { MessageBlock } from "@/components/ChatMessageBubble";
import type { ReactNode, KeyboardEvent } from "react";

import { api } from "@/api";
import ChatInputArea from "@/components/ChatInputArea";
import ChatMessageBubble from "@/components/ChatMessageBubble";
import ModelStatusDisplay from "@/components/ModelStatusDisplay";
import ToolsPanel from "@/components/ToolsPanel";

export interface ChatMessage {
    role: "user" | "assistant" | "system";
    content: string;
    reasoning_content?: string | null;
    blocks?: MessageBlock[] | null;
    created_at?: string;
}

export interface ModelStatus {
    state: "loaded" | "not_loaded" | "unavailable";
    message: string;
    provider?: string;
    model?: string;
    checked_at?: string;
}

export interface StreamEvent {
    type: string;
    delta?: {
        type?: string;
        text?: string;
        thinking?: string;
        reasoning?: string;
        partial_json?: string;
    };
    content_block?: { type?: string };
    message?: string;
    text?: string;
    tools?: string[];
    phase?: string;
    [key: string]: unknown;
}

interface ToolPanel {
    pretext: string;
    tools: string[];
}

export interface ChatInterfaceHandle {
    sendMessage: (messageOverride?: string) => Promise<void>;
}

export interface ChatInterfaceProps {
    chatEndpoint: string;
    statusUrl: string;
    warmupUrl: string;
    initialMessages: ChatMessage[];
    isAuthenticated: boolean;
    shouldAutoStart?: boolean;
    autoStartMessage?: string;
    /** Extra fields merged into the POST body on every send. Stable reference preferred. */
    extraPayload?: { [key: string]: string | null | undefined };
    slots?: {
        header?: ReactNode;
        aboveMessages?: ReactNode;
        aboveInput?: ReactNode;
        beforeSend?: ReactNode;
        afterSend?: ReactNode;
    };
    /** Called for every parsed SSE event, including tool_use_progress and page_reload. */
    onEvent?: (event: StreamEvent) => void;
    /** Called when streaming finishes (success or error). */
    onStreamEnd?: () => void;
    /** Called whenever the message list changes (useful for scanning message content). */
    onMessagesChange?: (messages: ChatMessage[]) => void;
    /** Called when the model status changes (useful for driving status badges in the parent). */
    onModelStatusChange?: (status: ModelStatus | null) => void;
    /** Called immediately when the stream response arrives (before body is read). */
    onStreamResponse?: (response: Response) => void;
}

type VirtualItem =
    | { _kind: "above-messages" }
    | { _kind: "message"; msg: ChatMessage; msgIndex: number }
    | {
          _kind: "stream";
          blocks: MessageBlock[];
          toolPanels: ToolPanel[];
      };

const EmptyPlaceholder = () => (
    <Box
        sx={{
            border: "1px dashed",
            borderColor: "divider",
            py: 3,
            px: 2,
            mx: 3,
            mt: 2.5,
            textAlign: "center",
            color: "text.secondary",
        }}
    >
        Send the first message to start the conversation.
    </Box>
);

export default forwardRef<ChatInterfaceHandle, ChatInterfaceProps>(
    function ChatInterface(
        {
            chatEndpoint,
            statusUrl,
            warmupUrl,
            initialMessages,
            isAuthenticated,
            shouldAutoStart = false,
            autoStartMessage,
            extraPayload,
            slots,
            onEvent,
            onStreamEnd,
            onModelStatusChange,
            onStreamResponse,
            onMessagesChange,
        },
        ref,
    ) {
        const theme = useTheme();
        const isMobile = useMediaQuery(theme.breakpoints.down("sm"));

        const [messages, setMessages] =
            useState<ChatMessage[]>(initialMessages);
        const [streamingBlocks, setStreamingBlocks] = useState<MessageBlock[]>(
            [],
        );
        const [streamingToolPanels, setStreamingToolPanels] = useState<
            ToolPanel[]
        >([]);
        const [isStreaming, setIsStreaming] = useState(false);
        const [modelStatus, setModelStatus] = useState<ModelStatus | null>(
            null,
        );
        const [isCheckingModelStatus, setIsCheckingModelStatus] =
            useState(false);
        const [isWarmingModel, setIsWarmingModel] = useState(false);
        const [loadingMessage, setLoadingMessage] = useState("");
        const [error, setError] = useState("");
        const [messageText, setMessageText] = useState("");

        const virtuosoRef = useRef<VirtuosoHandle>(null);
        const hasAutoStarted = useRef(false);
        const extraPayloadRef = useRef(extraPayload);
        const onModelStatusChangeRef = useRef(onModelStatusChange);
        const onMessagesChangeRef = useRef(onMessagesChange);

        // Keep callback refs fresh without mutating during render
        useEffect(() => {
            extraPayloadRef.current = extraPayload;
        });
        useEffect(() => {
            onModelStatusChangeRef.current = onModelStatusChange;
        });
        useEffect(() => {
            onMessagesChangeRef.current = onMessagesChange;
        });

        // Capture the initial last index so Virtuoso starts scrolled to the bottom.
        // Add 1 when aboveMessages is present because it occupies virtual index 0.
        const [initialTopMostItemIndex] = useState(() => {
            const offset = slots?.aboveMessages ? 1 : 0;
            return initialMessages.length > 0
                ? initialMessages.length - 1 + offset
                : 0;
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

        const updateModelStatus = (status: ModelStatus | null) => {
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
                setLoadingMessage(
                    "Loading model. This can take a little while...",
                );

                try {
                    const wp = await api.post<{ status?: ModelStatus }>(
                        warmupUrl,
                    );
                    if (wp.status) updateModelStatus(wp.status);
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

        const sendMessage = useCallback(
            async (messageOverride?: string) => {
                const text = messageOverride ?? messageText.trim();

                if (!text && !shouldAutoStart) return;
                if (isStreaming) return;

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
                    setMessageText("");
                }

                setIsStreaming(true);
                setStreamingBlocks([]);
                setStreamingToolPanels([]);
                setError("");

                let liveBlocks: MessageBlock[] = [];

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
                    flushSync(() => {
                        setStreamingBlocks([...liveBlocks]);
                    });
                };

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
                    )) {
                        if (!jsonStr || jsonStr === "[DONE]") continue;

                        let event: StreamEvent;
                        try {
                            event = JSON.parse(jsonStr) as StreamEvent;
                        } catch {
                            continue;
                        }

                        onEvent?.(event);

                        if (
                            event.type === "reasoning_block_delta" &&
                            event.delta?.reasoning
                        ) {
                            setLoadingMessage("");
                            appendToBlocks("reasoning", event.delta.reasoning);
                        } else if (event.type === "content_block_delta") {
                            if (
                                event.delta?.type === "thinking_delta" &&
                                event.delta.thinking
                            ) {
                                setLoadingMessage("");
                                appendToBlocks(
                                    "reasoning",
                                    event.delta.thinking,
                                );
                            } else if (event.delta?.text) {
                                setLoadingMessage("");
                                appendToBlocks("text", event.delta.text);
                            }
                        } else if (event.type === "tool_use_progress") {
                            setStreamingToolPanels((prev) => [
                                ...prev,
                                {
                                    pretext: event.text ?? "",
                                    tools: event.tools ?? [],
                                },
                            ]);
                            liveBlocks = [];
                            setStreamingBlocks([]);
                        } else if (event.type === "status") {
                            setLoadingMessage(
                                event.message ??
                                    "Waiting for model response...",
                            );
                        } else if (event.type === "error") {
                            throw new Error(event.message ?? "Unknown error");
                        }
                    }

                    const finalText = liveBlocks
                        .filter((b) => b.type === "text")
                        .map((b) => b.content)
                        .join("");

                    if (finalText || liveBlocks.length > 0) {
                        setMessages((prev) => {
                            const next = [
                                ...prev,
                                {
                                    role: "assistant" as const,
                                    content: finalText,
                                    blocks:
                                        liveBlocks.length > 0
                                            ? liveBlocks
                                            : null,
                                    created_at: new Date().toISOString(),
                                },
                            ];
                            onMessagesChangeRef.current?.(next);
                            return next;
                        });
                    }

                    onStreamEnd?.();
                } catch (err) {
                    setError(
                        err instanceof Error
                            ? err.message
                            : "Unable to send message right now.",
                    );
                } finally {
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
                shouldAutoStart,
                onEvent,
                onStreamEnd,
            ],
        );

        // Expose sendMessage for parent imperative use (e.g. auto-start from Show.tsx)
        useImperativeHandle(ref, () => ({ sendMessage }), [sendMessage]);

        // Auto-start the conversation on mount
        useEffect(() => {
            if (shouldAutoStart && !hasAutoStarted.current) {
                hasAutoStarted.current = true;
                void sendMessage(autoStartMessage ?? "");
            }
            // eslint-disable-next-line react-hooks/exhaustive-deps
        }, []);

        const handleKeyDown = (e: KeyboardEvent<HTMLDivElement>) => {
            if (e.key === "Enter" && (e.ctrlKey || e.metaKey)) {
                e.preventDefault();
                void sendMessage();
            }
        };

        // Build the virtual item list: optional above-messages header + past messages + optional live streaming item
        const virtualItems: VirtualItem[] = [];
        if (slots?.aboveMessages) {
            virtualItems.push({ _kind: "above-messages" });
        }
        virtualItems.push(
            ...messages.map((msg, msgIndex) => ({
                _kind: "message" as const,
                msg,
                msgIndex,
            })),
        );
        if (isStreaming) {
            virtualItems.push({
                _kind: "stream",
                blocks: streamingBlocks,
                toolPanels: streamingToolPanels,
            });
        }

        const virtuosoHeight = isMobile
            ? "calc(100dvh - 320px)"
            : "calc(100vh - 480px)";
        const virtuosoMinHeight = isMobile ? 200 : 300;

        return (
            <>
                {slots?.header ?? null}
                <Card>
                    <CardContent sx={{ p: 0 }}>
                        <Virtuoso<VirtualItem>
                            ref={virtuosoRef}
                            style={{
                                height: virtuosoHeight,
                                minHeight: virtuosoMinHeight,
                            }}
                            data={virtualItems}
                            followOutput="smooth"
                            initialTopMostItemIndex={initialTopMostItemIndex}
                            components={{
                                EmptyPlaceholder,
                            }}
                            itemContent={(_, item) => {
                                if (item._kind === "above-messages") {
                                    return (
                                        <Box
                                            sx={{
                                                px: { xs: 1.5, md: 3 },
                                                pt: 2.5,
                                                pb: 1,
                                            }}
                                        >
                                            {slots?.aboveMessages}
                                        </Box>
                                    );
                                }

                                if (item._kind === "message") {
                                    return (
                                        <Box
                                            sx={{
                                                px: { xs: 1.5, md: 3 },
                                                py: 1.5,
                                            }}
                                        >
                                            <ChatMessageBubble
                                                role={item.msg.role}
                                                content={item.msg.content}
                                                blocks={item.msg.blocks ?? null}
                                                reasoningContent={
                                                    item.msg
                                                        .reasoning_content ??
                                                    null
                                                }
                                                isAuthenticated={
                                                    isAuthenticated
                                                }
                                            />
                                        </Box>
                                    );
                                }

                                // Streaming item: tool panels + live assistant bubble
                                const lastBlock =
                                    item.blocks.length > 0
                                        ? item.blocks[item.blocks.length - 1]
                                        : null;
                                return (
                                    <Box
                                        sx={{
                                            px: { xs: 1.5, md: 3 },
                                            py: 1.5,
                                        }}
                                    >
                                        {item.toolPanels.length > 0 ? (
                                            <Box
                                                sx={{
                                                    bgcolor: "grey.50",
                                                    p: 2,
                                                    mb: 1.5,
                                                }}
                                            >
                                                {item.toolPanels.map(
                                                    (panel, i) => (
                                                        <ToolsPanel
                                                            key={i}
                                                            pretext={
                                                                panel.pretext
                                                            }
                                                            tools={panel.tools}
                                                            isActive={false}
                                                        />
                                                    ),
                                                )}
                                                {item.blocks.length === 0 ? (
                                                    <ToolsPanel
                                                        pretext=""
                                                        tools={[]}
                                                        isActive
                                                    />
                                                ) : null}
                                            </Box>
                                        ) : null}
                                        <ChatMessageBubble
                                            role="assistant"
                                            content=""
                                            isStreaming
                                            blocks={
                                                item.blocks.length > 0
                                                    ? item.blocks
                                                    : null
                                            }
                                            activeBlockType={
                                                lastBlock?.type ?? null
                                            }
                                            isAuthenticated={isAuthenticated}
                                        />
                                    </Box>
                                );
                            }}
                        />

                        {slots?.aboveInput}

                        <Divider />

                        <ModelStatusDisplay
                            isCheckingModelStatus={isCheckingModelStatus}
                            isWarmingModel={isWarmingModel}
                            loadingMessage={loadingMessage}
                            modelStatus={modelStatus}
                            error={error}
                        />

                        <ChatInputArea
                            messageText={messageText}
                            onChange={setMessageText}
                            onKeyDown={handleKeyDown}
                            onSubmit={() => void sendMessage()}
                            disabled={
                                isStreaming ||
                                isCheckingModelStatus ||
                                isWarmingModel ||
                                modelStatus?.state === "unavailable"
                            }
                            slots={{
                                beforeSend: slots?.beforeSend,
                                afterSend: slots?.afterSend,
                            }}
                        />
                    </CardContent>
                </Card>
            </>
        );
    },
);
