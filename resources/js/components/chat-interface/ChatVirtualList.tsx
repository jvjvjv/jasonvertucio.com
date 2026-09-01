import Box from "@mui/material/Box";
import { forwardRef } from "react";
import { Virtuoso } from "react-virtuoso";

import { CHAT_COLUMN_MAX_WIDTH } from "./chatColumn";
import EmptyPlaceholder from "./EmptyPlaceholder";
import NoReplyNotice from "./NoReplyNotice";

import type { ChatMessage } from "@/components/ChatInterface";
import type { MessageBlock } from "@/components/ChatMessageBubble";
import type { ToolPanel } from "@/components/ToolsPanel";
import type { ReactNode } from "react";
import type { ContextProp, VirtuosoHandle } from "react-virtuoso";

import ChatMessageBubble from "@/components/ChatMessageBubble";

type VirtualItem =
    | { _kind: "above-messages" }
    | { _kind: "message"; msg: ChatMessage; msgIndex: number }
    | { _kind: "no-reply" }
    | {
          _kind: "stream";
          blocks: MessageBlock[];
          toolPanels: ToolPanel[];
      };

/**
 * Whether the transcript ends on an unanswered user message.
 *
 * Since code-talker 0.15.0 the server persists an interrupted turn instead of
 * discarding it, so a new one leaves an assistant row flagged `incomplete`
 * rather than nothing at all — that row is what explains itself, and this stays
 * false. What remains for this to catch is conversations from before the
 * upgrade, whose dropped turns left no row behind, plus a live turn that ends
 * without the server's reply reaching us. While a turn is streaming the
 * trailing user message is answered by the stream item, so this stays false.
 */
export function hasUnansweredTrailingMessage(
    messages: ChatMessage[],
    isStreaming: boolean,
): boolean {
    return (
        !isStreaming &&
        messages.length > 0 &&
        messages[messages.length - 1].role === "user"
    );
}

interface ChatVirtualListProps {
    messages: ChatMessage[];
    isStreaming: boolean;
    streamingBlocks: MessageBlock[];
    streamingToolPanels: ToolPanel[];
    isAuthenticated: boolean;
    isMobile: boolean;
    initialTopMostItemIndex: number;
    aboveMessagesSlot?: ReactNode;
    padding: number;
}

interface ListContext {
    bottomPadding: number;
}

function ListFooter({ context }: ContextProp<ListContext>) {
    return <Box sx={{ height: `${context.bottomPadding}px` }} />;
}

/** Virtualized message list: optional above-messages header, past messages, and the live streaming item. */
export default forwardRef<VirtuosoHandle, ChatVirtualListProps>(
    function ChatVirtualList(
        {
            messages,
            isStreaming,
            streamingBlocks,
            streamingToolPanels,
            isAuthenticated,
            isMobile,
            initialTopMostItemIndex,
            aboveMessagesSlot,
            padding,
        },
        ref,
    ) {
        const virtualItems: VirtualItem[] = [];
        if (aboveMessagesSlot) {
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
        } else if (hasUnansweredTrailingMessage(messages, isStreaming)) {
            virtualItems.push({ _kind: "no-reply" });
        }

        const chromeHeight = isMobile ? 300 : 320;
        const virtuosoHeight = `calc(100dvh - ${chromeHeight}px)`;
        const virtuosoMinHeight = isMobile ? 200 : 300;

        const chatBubbleStyle = {
            maxWidth: CHAT_COLUMN_MAX_WIDTH,
            margin: "0 auto",
        };

        return (
            <Virtuoso<VirtualItem, ListContext>
                ref={ref}
                style={{
                    height: virtuosoHeight,
                    minHeight: virtuosoMinHeight,
                    margin: "0 auto",
                    width: "100%",
                }}
                data={virtualItems}
                followOutput="smooth"
                initialTopMostItemIndex={initialTopMostItemIndex}
                context={{ bottomPadding: padding }}
                components={{
                    EmptyPlaceholder,
                    Footer: ListFooter,
                }}
                itemContent={(_, item) => {
                    if (item._kind === "above-messages") {
                        return (
                            <Box
                                sx={{
                                    px: { xs: 1.5, md: 3 },
                                    pt: 2.5,
                                }}
                            >
                                {aboveMessagesSlot}
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
                                    toolPanels={
                                        item.msg.tool_panels ?? undefined
                                    }
                                    maxWidth="100%"
                                    reasoningContent={
                                        item.msg.reasoning_content ?? null
                                    }
                                    isAuthenticated={isAuthenticated}
                                    isManualEdit={
                                        item.msg.metadata?.origin ===
                                        "manual_edit"
                                    }
                                    isIncomplete={item.msg.incomplete ?? false}
                                    sx={chatBubbleStyle}
                                />
                            </Box>
                        );
                    }

                    if (item._kind === "no-reply") {
                        return (
                            <Box
                                sx={{
                                    px: { xs: 1.5, md: 3 },
                                    py: 1.5,
                                }}
                            >
                                <NoReplyNotice />
                            </Box>
                        );
                    }

                    // Streaming item: the live assistant bubble, which renders
                    // this turn's tool panels inside itself so they share the
                    // bubble's width and chrome.
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
                            <ChatMessageBubble
                                role="assistant"
                                content=""
                                isStreaming
                                maxWidth="100%"
                                blocks={
                                    item.blocks.length > 0 ? item.blocks : null
                                }
                                toolPanels={item.toolPanels}
                                activeBlockType={lastBlock?.type ?? null}
                                isAuthenticated={isAuthenticated}
                                sx={chatBubbleStyle}
                            />
                        </Box>
                    );
                }}
            />
        );
    },
);
