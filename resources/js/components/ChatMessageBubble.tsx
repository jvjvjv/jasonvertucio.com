import Box from "@mui/material/Box";
import { useState } from "react";

import { markdownSx } from "../admin/utils/markdownSx";
import mergeSx from "../utils/mergeSx";

import BlockContent from "./chat-message-bubble/BlockContent";
import LegacyContent from "./chat-message-bubble/LegacyContent";

import type { SxProps, SystemStyleObject, Theme } from "@mui/system";

export interface MessageBlock {
    type: "text" | "reasoning";
    content: string;
}

interface ChatMessageBubbleProps {
    content?: string;
    role: string;
    maxWidth?: string;
    variant?: "chat" | "history";
    sentAt?: string | null;
    isStreaming?: boolean;
    sx?: SxProps<Theme>;
    /** Whether the current user is authenticated. If false, reasoning/thinking boxes are hidden. */
    isAuthenticated?: boolean;
    /** Full interleaved block sequence. When present, renders instead of `content`. */
    blocks?: MessageBlock[] | null;
    /** Which block type is currently being streamed (only meaningful when isStreaming). */
    activeBlockType?: "text" | "reasoning" | null;
    /** Legacy single-blob reasoning (used when blocks is absent). */
    reasoningContent?: string | null;
}

export const userMarkdownOverrides = {
    "& a": { color: "inherit" },
    "& pre": {
        bgcolor: "rgba(255,255,255,0.2)",
        whiteSpace: "pre-pre-wrap",
    },
    "& blockquote": {
        borderLeftColor: "rgba(255,255,255,0.55)",
        color: "inherit",
    },
    "& hr": { borderTopColor: "rgba(255,255,255,0.35)" },
};

export default function ChatMessageBubble({
    content = "",
    role,
    maxWidth = "80%",
    variant = "chat",
    sentAt = null,
    isStreaming = false,
    isAuthenticated = false,
    blocks = null,
    activeBlockType = null,
    reasoningContent = null,
    sx,
}: ChatMessageBubbleProps) {
    const isUser = role === "user";
    const isChatVariant = variant === "chat";

    const baseBubbleSx = {
        alignSelf: isChatVariant
            ? isUser
                ? "flex-end"
                : "flex-start"
            : "stretch",
        maxWidth: isChatVariant ? maxWidth : "100%",
        bgcolor: isChatVariant
            ? isUser
                ? "primary.main"
                : "grey.100"
            : "transparent",
        color: isChatVariant
            ? isUser
                ? "primary.contrastText"
                : "text.primary"
            : "text.primary",
        borderRadius: isChatVariant ? 2 : 0,
        px: isChatVariant ? 2 : 0,
        py: isChatVariant ? 1 : 0,
        "& p:first-of-type": { mt: 0 },
    };

    const [wordWrap, setWordWrap] = useState(true);

    const preWrapSx: SystemStyleObject<Theme> = {
        "& pre": {
            ...markdownSx["& pre"],
            whiteSpace: wordWrap ? "pre-wrap" : "pre",
            overflowX: wordWrap ? "hidden" : undefined,
            cursor: "pointer",
            userSelect: "all",
        },
    };

    const handlePreDblClick = (e: React.MouseEvent<HTMLElement>) => {
        let el = e.target as HTMLElement | null;
        while (el && el !== e.currentTarget) {
            if (el.tagName === "PRE") {
                setWordWrap((prev) => !prev);
                break;
            }
            el = el.parentElement;
        }
    };

    if (!isUser && blocks && blocks.length > 0) {
        return (
            <Box
                sx={mergeSx(baseBubbleSx, sx)}
                onDoubleClick={handlePreDblClick}
            >
                <BlockContent
                    blocks={blocks}
                    isStreaming={isStreaming}
                    isAuthenticated={isAuthenticated}
                    activeBlockType={activeBlockType}
                    sentAt={sentAt}
                    preWrapSx={preWrapSx}
                />
            </Box>
        );
    }

    return (
        <Box
            sx={mergeSx({ ...baseBubbleSx, position: "relative" }, sx)}
            onDoubleClick={handlePreDblClick}
        >
            <LegacyContent
                content={content}
                isUser={isUser}
                isChatVariant={isChatVariant}
                isStreaming={isStreaming}
                isAuthenticated={isAuthenticated}
                activeBlockType={activeBlockType}
                reasoningContent={reasoningContent}
                sentAt={sentAt}
                preWrapSx={preWrapSx}
            />
        </Box>
    );
}
