import SendIcon from "@mui/icons-material/Send";
import Alert from "@mui/material/Alert";
import Box from "@mui/material/Box";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";
import Divider from "@mui/material/Divider";
import Stack from "@mui/material/Stack";
import TextField from "@mui/material/TextField";
import {
    useCallback,
    useEffect,
    useImperativeHandle,
    useRef,
    useState,
    forwardRef,
} from "react";
import { flushSync } from "react-dom";

import type { MessageBlock } from "@/components/ChatMessageBubble";
import type { ReactNode, KeyboardEvent } from "react";

import { api } from "@/api";
import ChatMessageBubble from "@/components/ChatMessageBubble";
import ResponsiveButton from "@/components/ResponsiveButton";
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

        const messagesEndRef = useRef<HTMLDivElement>(null);
        const hasAutoStarted = useRef(false);
        // Always-fresh ref so sendMessage closure never goes stale on extraPayload
        const extraPayloadRef = useRef(extraPayload);
        extraPayloadRef.current = extraPayload;

        useEffect(() => {
            setMessages(initialMessages);
            onMessagesChangeRef.current?.(initialMessages);
        }, [initialMessages]);

        useEffect(() => {
            messagesEndRef.current?.scrollIntoView({ behavior: "smooth" });
        }, [messages, streamingBlocks]);

        const onModelStatusChangeRef = useRef(onModelStatusChange);
        onModelStatusChangeRef.current = onModelStatusChange;

        const onMessagesChangeRef = useRef(onMessagesChange);
        onMessagesChangeRef.current = onMessagesChange;

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

        const isUnavailable = modelStatus?.state === "unavailable";
        const lastStreamingBlock =
            streamingBlocks.length > 0
                ? streamingBlocks[streamingBlocks.length - 1]
                : null;

        return (
            <Card>
                <CardContent sx={{ p: 0 }}>
                    <Box
                        sx={{
                            display: "flex",
                            flexDirection: "column",
                            gap: 2,
                            px: 3,
                            py: 2.5,
                        }}
                    >
                        {slots?.aboveMessages}

                        {messages.length === 0 && !isStreaming ? (
                            <Box
                                sx={{
                                    border: "1px dashed",
                                    borderColor: "divider",
                                    py: 3,
                                    px: 2,
                                    textAlign: "center",
                                    color: "text.secondary",
                                }}
                            >
                                Send the first message to start the
                                conversation.
                            </Box>
                        ) : (
                            messages.map((message, index) => (
                                <ChatMessageBubble
                                    key={index}
                                    role={message.role}
                                    content={message.content}
                                    blocks={message.blocks ?? null}
                                    reasoningContent={
                                        message.reasoning_content ?? null
                                    }
                                    isAuthenticated={isAuthenticated}
                                />
                            ))
                        )}
                    </Box>

                    {streamingToolPanels.length > 0 || isStreaming ? (
                        <>
                            <Divider />
                            <Box sx={{ px: 3, py: 2.5, bgcolor: "grey.50" }}>
                                {streamingToolPanels.map((panel, i) => (
                                    <ToolsPanel
                                        key={i}
                                        pretext={panel.pretext}
                                        tools={panel.tools}
                                        isActive={false}
                                    />
                                ))}
                                {isStreaming &&
                                streamingBlocks.length === 0 &&
                                streamingToolPanels.length > 0 ? (
                                    <ToolsPanel
                                        pretext=""
                                        tools={[]}
                                        isActive
                                    />
                                ) : null}
                                {isStreaming ? (
                                    <ChatMessageBubble
                                        role="assistant"
                                        content=""
                                        isStreaming
                                        blocks={
                                            streamingBlocks.length > 0
                                                ? streamingBlocks
                                                : null
                                        }
                                        activeBlockType={
                                            lastStreamingBlock?.type ?? null
                                        }
                                        isAuthenticated={isAuthenticated}
                                    />
                                ) : null}
                            </Box>
                        </>
                    ) : null}

                    {slots?.aboveInput}

                    <Divider />
                    <Box
                        component="form"
                        sx={{ px: 3, py: 2.5 }}
                        onSubmit={(e) => {
                            e.preventDefault();
                            void sendMessage();
                        }}
                    >
                        <Stack spacing={2}>
                            {slots?.beforeSend}

                            <TextField
                                label="Your message"
                                multiline
                                minRows={3}
                                value={messageText}
                                onChange={(e) => {
                                    setMessageText(e.target.value);
                                }}
                                onKeyDown={handleKeyDown}
                                fullWidth
                                disabled={isStreaming}
                            />

                            {isCheckingModelStatus ? (
                                <Alert severity="info">
                                    Checking model status...
                                </Alert>
                            ) : null}

                            {isWarmingModel || loadingMessage ? (
                                <Alert severity="info">
                                    {loadingMessage ||
                                        "Loading model. This can take a little while..."}
                                </Alert>
                            ) : null}

                            {modelStatus?.state === "loaded" &&
                            !isWarmingModel &&
                            !isCheckingModelStatus ? (
                                <Alert severity="success">
                                    Model is ready.
                                </Alert>
                            ) : null}

                            {modelStatus?.state === "not_loaded" &&
                            !isWarmingModel ? (
                                <Alert severity="warning">
                                    {modelStatus.message}
                                </Alert>
                            ) : null}

                            {error ? (
                                <Alert severity="error">{error}</Alert>
                            ) : null}

                            <Box
                                sx={{
                                    display: "flex",
                                    justifyContent: "flex-end",
                                    alignItems: "center",
                                    gap: 2,
                                }}
                            >
                                {slots?.afterSend}
                                <ResponsiveButton
                                    type="submit"
                                    icon={<SendIcon />}
                                    color="primary"
                                    variant="contained"
                                    disabled={
                                        isStreaming ||
                                        isCheckingModelStatus ||
                                        isWarmingModel ||
                                        isUnavailable
                                    }
                                    label="Send Message"
                                    onClick={() => {
                                        void sendMessage();
                                    }}
                                />
                            </Box>
                        </Stack>
                    </Box>

                    <div ref={messagesEndRef} />
                </CardContent>
            </Card>
        );
    },
);
