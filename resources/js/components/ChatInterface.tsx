import Box from "@mui/material/Box";
import Card from "@mui/material/Card";
import { useTheme } from "@mui/material/styles";
import useMediaQuery from "@mui/material/useMediaQuery";
import {
    useEffect,
    useImperativeHandle,
    useRef,
    useState,
    forwardRef,
} from "react";
import { type VirtuosoHandle } from "react-virtuoso";

import type { MessageBlock } from "@/components/ChatMessageBubble";
import type { ToolPanel } from "@/components/ToolsPanel";
import type { ChatStreamEvent } from "@/types/code-talker";
import type { ReactNode, KeyboardEvent } from "react";

import { api } from "@/api";
import { resolveChatStatus } from "@/components/chat-interface/chatStatus";
import ChatVirtualList, {
    hasUnansweredTrailingMessage,
} from "@/components/chat-interface/ChatVirtualList";
import useCardTop from "@/components/chat-interface/useCardTop";
import ChatInputArea from "@/components/ChatInputArea";
import useChatStream from "@/hooks/useChatStream";
import useModelStatus from "@/hooks/useModelStatus";
import useSessionExpiry from "@/hooks/useSessionExpiry";
import useVoice from "@/hooks/useVoice";

// Used when no session deadline is supplied, so the hook always has a
// stable initial value to call (rules-of-hooks) but effectively never fires.
const FAR_FUTURE = new Date(8.64e15).toISOString();

/**
 * Deliberately NOT the package's `ChatMessage` from `@/types/code-talker`.
 *
 * That type describes what the server sends; this one also covers messages the
 * client builds mid-turn, so it diverges in five ways: `role` allows "system",
 * `reasoning_content`/`blocks` are optional rather than nullable-required,
 * `incomplete` is optional because a client-built message has no server verdict
 * yet, `created_at` is stamped locally when a message is appended
 * optimistically, and `tool_panels` carries this turn's tool activity — a
 * host-only concern assembled from the stream's `tool_use_progress` frames.
 */
export interface ChatMessage {
    role: "user" | "assistant" | "system";
    content: string;
    reasoning_content?: string | null;
    blocks?: MessageBlock[] | null;
    tool_panels?: ToolPanel[] | null;
    created_at?: string;
    metadata?: { [key: string]: unknown } | null;
    /**
     * The reply was never finished — the browser hung up, or the server's
     * duration guard cut it off. Since code-talker 0.15.0 such a turn is
     * persisted rather than discarded, so `content` may be empty or stop
     * mid-sentence. Render it as interrupted, not as an answer.
     */
    incomplete?: boolean;
}

export interface ModelStatus {
    state: "loaded" | "not_loaded" | "unavailable";
    message: string;
    provider?: string;
    model?: string;
    checked_at?: string;
}

/**
 * Every frame the chat stream can deliver.
 *
 * `tool_use_progress` and `page_reload` were declared here as host-only events
 * — `app/Services/TargetedResumeService.php` emits both from its own turn loop.
 * code-talker 0.13.0 adopted them, so they now arrive as part of
 * `ChatStreamEvent` and this is an alias kept for the call sites importing it.
 *
 * Deliberately has no index signature — an unrecognized property should be a
 * build error, not `unknown`. Unhandled event *types* are still inert at
 * runtime, so a newer package stays forward-compatible.
 */
export type StreamEvent = ChatStreamEvent;

export interface ChatInterfaceHandle {
    sendMessage: (messageOverride?: string) => Promise<void>;
}

export interface ChatInterfaceProps {
    chatEndpoint: string;
    statusUrl: string;
    warmupUrl: string;
    initialMessages: ChatMessage[];
    isAuthenticated: boolean;
    /** ISO timestamp of when the current session is expected to expire. Omit to disable expiry tracking. */
    sessionExpiresAt?: string;
    shouldAutoStart?: boolean;
    autoStartMessage?: string;
    messagePadding?: number;
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

export default forwardRef<ChatInterfaceHandle, ChatInterfaceProps>(
    function ChatInterface(
        {
            chatEndpoint,
            statusUrl,
            warmupUrl,
            initialMessages,
            isAuthenticated,
            sessionExpiresAt,
            shouldAutoStart = false,
            messagePadding = 16,
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

        const { isExpired, extend, markExpired } = useSessionExpiry(
            sessionExpiresAt ?? FAR_FUTURE,
        );

        useEffect(() => {
            api.setSessionHandlers({
                onActivity: extend,
                onSessionExpired: markExpired,
            });
            return () => {
                api.setSessionHandlers({});
            };
        }, [extend, markExpired]);

        const {
            modelStatus,
            isCheckingModelStatus,
            isWarmingModel,
            loadingMessage,
            setLoadingMessage,
        } = useModelStatus(statusUrl, warmupUrl, onModelStatusChange);

        const [messageText, setMessageText] = useState("");
        const voice = useVoice();

        const {
            messages,
            streamingBlocks,
            streamingToolPanels,
            isStreaming,
            error,
            sendMessage,
            stopStreaming,
        } = useChatStream({
            chatEndpoint,
            initialMessages,
            messageText,
            onMessageSent: () => {
                setMessageText("");
            },
            isExpired,
            shouldAutoStart,
            autoStartMessage,
            extraPayload,
            onEvent,
            onStreamEnd,
            onMessagesChange,
            onStreamResponse,
            setLoadingMessage,
        });

        const virtuosoRef = useRef<VirtuosoHandle>(null);
        const scrollerRef = useRef<HTMLElement | null>(null);
        const isAtBottomRef = useRef(true);
        const dockRef = useRef<HTMLDivElement>(null);
        const cardRef = useRef<HTMLDivElement>(null);
        // The card fills the viewport from wherever the page's own chrome
        // leaves it; until that is measured, a typical chrome height stands in.
        const cardTop = useCardTop(cardRef);

        // Virtuoso's `followOutput` reacts to new items, not to its viewport
        // shrinking, so a growing composer would push the newest message
        // below the fold. When the dock changes height and the list was
        // showing its end, keep it there; a reader scrolled up keeps their
        // place.
        useEffect(() => {
            const dock = dockRef.current;
            if (!dock) {
                return;
            }

            let previousHeight = dock.getBoundingClientRect().height;
            const observer = new ResizeObserver(() => {
                const height = dock.getBoundingClientRect().height;
                const growth = height - previousHeight;
                previousHeight = height;

                if (growth === 0) {
                    return;
                }

                // Virtuoso may already have judged the shrunken viewport "not
                // at the bottom", so also accept a list whose only distance
                // from the end is what the dock just took.
                const scroller = scrollerRef.current;
                const distanceFromEnd = scroller
                    ? scroller.scrollHeight -
                      scroller.scrollTop -
                      scroller.clientHeight
                    : Infinity;
                const wasAtBottom =
                    isAtBottomRef.current ||
                    distanceFromEnd <= Math.max(growth, 0) + 8;

                if (wasAtBottom) {
                    virtuosoRef.current?.scrollTo({
                        top: Number.MAX_SAFE_INTEGER,
                    });
                }
            });

            observer.observe(dock);

            return () => {
                observer.disconnect();
            };
        }, []);

        // Capture the initial last index so Virtuoso starts scrolled to the bottom.
        // Add 1 when aboveMessages is present because it occupies virtual index 0,
        // and again when the transcript arrives ending on an unanswered message —
        // the notice explaining that is the last item, and landing above it would
        // put the explanation off-screen at exactly the moment it's needed.
        const [initialTopMostItemIndex] = useState(() => {
            const offset =
                (slots?.aboveMessages ? 1 : 0) +
                (hasUnansweredTrailingMessage(initialMessages, false) ? 1 : 0);
            return initialMessages.length > 0
                ? initialMessages.length - 1 + offset
                : 0;
        });

        // Expose sendMessage for parent imperative use (e.g. auto-start from Show.tsx)
        useImperativeHandle(ref, () => ({ sendMessage }), [sendMessage]);

        const handleKeyDown = (e: KeyboardEvent<HTMLDivElement>) => {
            if (e.key === "Enter" && (e.ctrlKey || e.metaKey)) {
                e.preventDefault();
                void sendMessage();
            } else if (e.key === "Escape" && isStreaming) {
                e.preventDefault();
                stopStreaming();
            }
        };

        const chatStatus = resolveChatStatus({
            isExpired,
            error,
            isStreaming,
            loadingMessage,
            streamingBlocks,
            streamingToolPanels,
            modelStatus,
            isCheckingModelStatus,
            isWarmingModel,
            isAuthenticated,
        });

        return (
            <>
                {slots?.header ?? null}
                <Card
                    ref={cardRef}
                    sx={{
                        display: "flex",
                        flexDirection: "column",
                        height: `calc(100dvh - ${cardTop ?? (isMobile ? 300 : 320)}px)`,
                        minHeight: isMobile ? 200 : 300,
                    }}
                >
                    <ChatVirtualList
                        ref={virtuosoRef}
                        messages={messages}
                        isStreaming={isStreaming}
                        streamingBlocks={streamingBlocks}
                        streamingToolPanels={streamingToolPanels}
                        isAuthenticated={isAuthenticated}
                        initialTopMostItemIndex={initialTopMostItemIndex}
                        aboveMessagesSlot={slots?.aboveMessages}
                        padding={messagePadding}
                        onAtBottomChange={(atBottom) => {
                            isAtBottomRef.current = atBottom;
                        }}
                        scrollerRef={(element) => {
                            scrollerRef.current =
                                element instanceof HTMLElement ? element : null;
                        }}
                    />

                    <Box ref={dockRef} sx={{ flex: "none" }}>
                        {slots?.aboveInput}

                        <Box sx={{ borderTop: 1, borderColor: "divider" }}>
                            <ChatInputArea
                                messageText={messageText}
                                onChange={setMessageText}
                                onKeyDown={handleKeyDown}
                                onSubmit={() => void sendMessage()}
                                disabled={
                                    isStreaming ||
                                    isCheckingModelStatus ||
                                    isWarmingModel ||
                                    modelStatus?.state === "unavailable" ||
                                    isExpired
                                }
                                isStreaming={isStreaming}
                                onStop={stopStreaming}
                                voice={voice}
                                status={chatStatus}
                                slots={{
                                    beforeSend: slots?.beforeSend,
                                    afterSend: slots?.afterSend,
                                }}
                            />
                        </Box>
                    </Box>
                </Card>
            </>
        );
    },
);
