import Box from "@mui/material/Box";
import { useState } from "react";

import { markdownSx } from "../admin/utils/markdownSx";
import mergeSx from "../utils/mergeSx";

import BlockContent from "./chat-message-bubble/BlockContent";
import CopyMessageButton from "./chat-message-bubble/CopyMessageButton";
import LegacyContent from "./chat-message-bubble/LegacyContent";
import ToolsPanel from "./ToolsPanel";

import type { ToolPanel } from "./ToolsPanel";
import type { MessageBlock } from "@/types/code-talker";
import type { SxProps, SystemStyleObject, Theme } from "@mui/system";

/**
 * Re-exported from the package contract rather than redeclared — the shapes are
 * identical, so this keeps the package as the single source of truth. If the
 * package ever adds a block type, it surfaces here as a type error instead of
 * drifting silently.
 */
export type { MessageBlock };

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
    /**
     * Tool activity for the turn, rendered at the top of the bubble so it shares
     * the message's width and chrome. Host-only and live-only: the tool event
     * wipes the block sequence, so nothing precedes these, and they are dropped
     * when the turn ends because the server never persists tool calls.
     */
    toolPanels?: ToolPanel[];
    /** Which block type is currently being streamed (only meaningful when isStreaming). */
    activeBlockType?: "text" | "reasoning" | null;
    /** Legacy single-blob reasoning (used when blocks is absent). */
    reasoningContent?: string | null;
    /** Marks this message as a manual out-of-band edit rather than something typed into chat. */
    isManualEdit?: boolean;
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
    "& table td": { padding: "0.25rem" },
};

/** Renders reasoning content as a markdown blockquote with a bold+italic "Reasoning" intro, for the copy button. */
function formatReasoningAsBlockquote(text: string): string {
    const quoted = text
        .split("\n")
        .map((line) => (line === "" ? ">" : `> ${line}`))
        .join("\n");

    return `> **_Reasoning_**\n>\n${quoted}`;
}

export default function ChatMessageBubble({
    content = "",
    role,
    maxWidth = "80%",
    variant = "chat",
    sentAt = null,
    isStreaming = false,
    isAuthenticated = false,
    blocks = null,
    toolPanels = [],
    activeBlockType = null,
    reasoningContent = null,
    isManualEdit = false,
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

    const hasBlocks = !isUser && !!blocks && blocks.length > 0;

    /** Plain text for the copy button — reasoning first, then the response, in the order shown. */
    const copyText = hasBlocks
        ? blocks
              .map((block) =>
                  block.type === "reasoning"
                      ? formatReasoningAsBlockquote(block.content)
                      : block.content,
              )
              .join("\n\n")
        : [
              !isUser && reasoningContent
                  ? formatReasoningAsBlockquote(reasoningContent)
                  : null,
              content,
          ]
              .filter((part): part is string => !!part)
              .join("\n\n");

    const body = hasBlocks ? (
        <BlockContent
            blocks={blocks}
            isStreaming={isStreaming}
            isAuthenticated={isAuthenticated}
            activeBlockType={activeBlockType}
            sentAt={sentAt}
            preWrapSx={preWrapSx}
        />
    ) : (
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
    );

    return (
        <Box
            // position: relative always, not just for LegacyContent's own
            // absolutely-positioned reasoning toggle — CopyMessageButton below
            // needs it too, for both content modes.
            sx={mergeSx({ ...baseBubbleSx, position: "relative" }, sx)}
            onDoubleClick={handlePreDblClick}
        >
            {isManualEdit && (
                <Box
                    sx={{
                        fontSize: "0.75rem",
                        fontWeight: 600,
                        opacity: 0.8,
                        mb: 0.5,
                    }}
                >
                    ✎ Edited manually
                </Box>
            )}
            {toolPanels.map((panel, i) => (
                <ToolsPanel
                    key={i}
                    pretext={panel.pretext}
                    tools={panel.tools}
                    input={panel.input}
                    output={panel.output}
                    successful={panel.successful}
                    isActive={
                        isStreaming && !hasBlocks && i === toolPanels.length - 1
                    }
                />
            ))}
            {body}
            {!isStreaming && (
                <CopyMessageButton
                    text={copyText}
                    color={
                        isChatVariant && isUser
                            ? "primary.contrastText"
                            : "text.disabled"
                    }
                />
            )}
        </Box>
    );
}
