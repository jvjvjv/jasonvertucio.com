import PsychologyIcon from "@mui/icons-material/Psychology";
import Box from "@mui/material/Box";
import IconButton from "@mui/material/IconButton";
import LinearProgress from "@mui/material/LinearProgress";
import Tooltip from "@mui/material/Tooltip";
import Typography from "@mui/material/Typography";
import { marked } from "marked";
import { useState } from "react";

import { markdownSx } from "../admin/utils/markdownSx";

import ReasoningPanel from "./ReasoningPanel";

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
    /** Whether the current user is authenticated. If false, reasoning/thinking boxes are hidden. */
    isAuthenticated?: boolean;
    /** Full interleaved block sequence. When present, renders instead of `content`. */
    blocks?: MessageBlock[] | null;
    /** Which block type is currently being streamed (only meaningful when isStreaming). */
    activeBlockType?: "text" | "reasoning" | null;
    /** Legacy single-blob reasoning (used when blocks is absent). */
    reasoningContent?: string | null;
}

function getRelativeSentLabel(
    sentAt: string | null | undefined,
): string | null {
    if (!sentAt) return null;
    const sentAtDate = new Date(sentAt);
    if (Number.isNaN(sentAtDate.getTime())) return null;
    const now = new Date();
    const diffInSeconds = Math.round(
        (sentAtDate.getTime() - now.getTime()) / 1000,
    );
    const abs = Math.abs(diffInSeconds);
    const fmt = new Intl.RelativeTimeFormat(undefined, { numeric: "auto" });
    if (abs < 60) return `sent ${fmt.format(diffInSeconds, "second")}`;
    if (abs < 3600)
        return `sent ${fmt.format(Math.round(diffInSeconds / 60), "minute")}`;
    if (abs < 86400)
        return `sent ${fmt.format(Math.round(diffInSeconds / 3600), "hour")}`;
    if (abs < 86400 * 30)
        return `sent ${fmt.format(Math.round(diffInSeconds / 86400), "day")}`;
    if (abs < 86400 * 365)
        return `sent ${fmt.format(Math.round(diffInSeconds / (86400 * 30)), "month")}`;
    return `sent ${fmt.format(Math.round(diffInSeconds / (86400 * 365)), "year")}`;
}

function getLocaleDateTime(sentAt: string | null | undefined): string | null {
    if (!sentAt) return null;
    const d = new Date(sentAt);
    if (Number.isNaN(d.getTime())) return null;
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: "medium",
        timeStyle: "short",
    }).format(d);
}

const userMarkdownOverrides = {
    "& a": { color: "inherit" },
    "& code": { bgcolor: "rgba(255,255,255,0.2)" },
    "& pre": { bgcolor: "rgba(255,255,255,0.2)" },
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
}: ChatMessageBubbleProps) {
    const isUser = role === "user";
    const isChatVariant = variant === "chat";
    const relativeSentLabel = getRelativeSentLabel(sentAt);
    const localeDateTime = getLocaleDateTime(sentAt);

    // Legacy single-blob reasoning state (when blocks is absent)
    const [legacyReasoningExpanded, setLegacyReasoningExpanded] =
        useState(false);

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

    const preWrapSx = {
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

    // ── Block-based rendering ──────────────────────────────────────────────
    if (!isUser && blocks && blocks.length > 0) {
        return (
            <Box sx={baseBubbleSx} onDoubleClick={handlePreDblClick}>
                {blocks.map((block, i) => {
                    const isLastBlock = i === blocks.length - 1;
                    const isActiveBlock = isStreaming && isLastBlock;

                    if (block.type === "reasoning") {
                        return (
                            isAuthenticated && (
                                <ReasoningPanel
                                    key={i}
                                    content={block.content}
                                    isActive={
                                        isActiveBlock &&
                                        activeBlockType === "reasoning"
                                    }
                                />
                            )
                        );
                    }

                    // Text block
                    return (
                        <Box
                            key={i}
                            sx={{
                                ...markdownSx,
                                ...preWrapSx,
                                mt: i > 0 ? 1 : 0,
                            }}
                        >
                            <div
                                style={{ wordBreak: "break-word" }}
                                dangerouslySetInnerHTML={{
                                    __html: marked.parse(
                                        block.content ||
                                            (isActiveBlock ? "" : ""),
                                        { breaks: true },
                                    ) as string,
                                }}
                            />
                            {isActiveBlock && activeBlockType === "text" ? (
                                <Box
                                    sx={{ mt: 1.5 }}
                                    role="status"
                                    aria-live="polite"
                                    aria-label="Assistant response is still streaming"
                                >
                                    <LinearProgress />
                                </Box>
                            ) : null}
                        </Box>
                    );
                })}
                {sentAt && relativeSentLabel && localeDateTime ? (
                    <Tooltip title={`Sent: ${localeDateTime}`} arrow>
                        <Typography
                            variant="caption"
                            sx={{
                                display: "inline-block",
                                mt: "2px",
                                lineHeight: 1,
                                color: "text.secondary",
                                cursor: "help",
                                opacity: 0.7,
                            }}
                        >
                            {relativeSentLabel}
                        </Typography>
                    </Tooltip>
                ) : null}
            </Box>
        );
    }

    // ── Legacy single-content rendering (no blocks) ────────────────────────
    const hasLegacyReasoning = !isUser && !!reasoningContent && isAuthenticated;

    return (
        <Box
            sx={{ ...baseBubbleSx, position: "relative" }}
            onDoubleClick={handlePreDblClick}
        >
            {/* Collapsed brain icon for legacy reasoning */}
            {hasLegacyReasoning && !isStreaming ? (
                <Tooltip
                    title={
                        legacyReasoningExpanded
                            ? "Hide reasoning"
                            : "Show reasoning"
                    }
                    placement="top"
                    arrow
                >
                    <IconButton
                        size="small"
                        onClick={() => {
                            setLegacyReasoningExpanded((p) => !p);
                        }}
                        aria-label={
                            legacyReasoningExpanded
                                ? "Hide reasoning"
                                : "Show reasoning"
                        }
                        sx={{
                            position: "absolute",
                            top: 4,
                            right: 4,
                            p: 0.5,
                            color: legacyReasoningExpanded
                                ? "primary.main"
                                : "text.disabled",
                            "&:hover": { color: "primary.main" },
                        }}
                    >
                        <PsychologyIcon sx={{ fontSize: 16 }} />
                    </IconButton>
                </Tooltip>
            ) : null}

            {hasLegacyReasoning ? (
                <ReasoningPanel
                    content={reasoningContent}
                    isActive={isStreaming && activeBlockType === "reasoning"}
                />
            ) : null}

            <Box
                sx={{
                    ...markdownSx,
                    ...preWrapSx,
                    ...(isChatVariant && isUser ? userMarkdownOverrides : {}),
                    pr: hasLegacyReasoning && !isStreaming ? 3 : 0,
                }}
            >
                <div
                    style={{ wordBreak: "break-word" }}
                    dangerouslySetInnerHTML={{
                        __html: marked.parse(content, {
                            breaks: true,
                        }) as string,
                    }}
                />
                {relativeSentLabel && localeDateTime ? (
                    <Tooltip title={`Sent: ${localeDateTime}`} arrow>
                        <Typography
                            variant="caption"
                            sx={{
                                display: "inline-block",
                                mt: "2px",
                                lineHeight: 1,
                                color: isChatVariant
                                    ? isUser
                                        ? "primary.contrastText"
                                        : "text.secondary"
                                    : "text.primary",
                                cursor: "help",
                                opacity: 0.7,
                            }}
                        >
                            {relativeSentLabel}
                        </Typography>
                    </Tooltip>
                ) : null}
                {isStreaming ? (
                    <Box
                        sx={{ mt: 1.5 }}
                        role="status"
                        aria-live="polite"
                        aria-label="Assistant response is still streaming"
                    >
                        <LinearProgress />
                    </Box>
                ) : null}
            </Box>
        </Box>
    );
}
