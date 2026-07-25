import Box from "@mui/material/Box";
import { forwardRef } from "react";
import { Virtuoso, type VirtuosoHandle } from "react-virtuoso";

import EmptyPlaceholder from "./EmptyPlaceholder";

import type { ChatMessage } from "@/components/ChatInterface";
import type { MessageBlock } from "@/components/ChatMessageBubble";
import type { ReactNode } from "react";

import ChatMessageBubble from "@/components/ChatMessageBubble";
import ToolsPanel from "@/components/ToolsPanel";

interface ToolPanel {
    pretext: string;
    tools: string[];
}

type VirtualItem =
    | { _kind: "above-messages" }
    | { _kind: "message"; msg: ChatMessage; msgIndex: number }
    | {
          _kind: "stream";
          blocks: MessageBlock[];
          toolPanels: ToolPanel[];
      };

interface ChatVirtualListProps {
    messages: ChatMessage[];
    isStreaming: boolean;
    streamingBlocks: MessageBlock[];
    streamingToolPanels: ToolPanel[];
    isAuthenticated: boolean;
    isMobile: boolean;
    initialTopMostItemIndex: number;
    aboveMessagesSlot?: ReactNode;
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
        }

        const chromeHeight = isMobile ? 300 : 320;
        const virtuosoHeight = `calc(100dvh - ${chromeHeight}px)`;
        const virtuosoMinHeight = isMobile ? 200 : 300;

        const chatBubbleStyle = {
            maxWidth: "840px",
            margin: "0 auto",
        };

        return (
            <Virtuoso<VirtualItem>
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
                                    maxWidth="100%"
                                    reasoningContent={
                                        item.msg.reasoning_content ?? null
                                    }
                                    isAuthenticated={isAuthenticated}
                                    sx={chatBubbleStyle}
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
                                    {item.toolPanels.map((panel, i) => (
                                        <ToolsPanel
                                            key={i}
                                            pretext={panel.pretext}
                                            tools={panel.tools}
                                            isActive={false}
                                        />
                                    ))}
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
                                maxWidth="100%"
                                blocks={
                                    item.blocks.length > 0 ? item.blocks : null
                                }
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
