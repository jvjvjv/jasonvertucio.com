import PsychologyIcon from "@mui/icons-material/Psychology";
import Box from "@mui/material/Box";
import IconButton from "@mui/material/IconButton";
import LinearProgress from "@mui/material/LinearProgress";
import Tooltip from "@mui/material/Tooltip";
import { useState } from "react";

import { markdownSx } from "../../admin/utils/markdownSx";
import { userMarkdownOverrides } from "../ChatMessageBubble";
import ReasoningPanel from "../ReasoningPanel";

import MarkdownContent from "./MarkdownContent";
import SentAtLabel from "./SentAtLabel";

import type { SystemStyleObject, Theme } from "@mui/system";

interface LegacyContentProps {
    content: string;
    isUser: boolean;
    isChatVariant: boolean;
    isStreaming: boolean;
    isAuthenticated: boolean;
    activeBlockType: "text" | "reasoning" | null;
    reasoningContent: string | null | undefined;
    sentAt: string | null | undefined;
    preWrapSx: SystemStyleObject<Theme>;
}

/** Renders a single-blob legacy message (pre-block-streaming format), with a collapsible reasoning panel. */
export default function LegacyContent({
    content,
    isUser,
    isChatVariant,
    isStreaming,
    isAuthenticated,
    activeBlockType,
    reasoningContent,
    sentAt,
    preWrapSx,
}: LegacyContentProps) {
    const [legacyReasoningExpanded, setLegacyReasoningExpanded] =
        useState(false);

    const hasLegacyReasoning = !isUser && !!reasoningContent && isAuthenticated;

    return (
        <>
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
                <MarkdownContent content={content} />
                <SentAtLabel
                    sentAt={sentAt}
                    color={
                        isChatVariant
                            ? isUser
                                ? "primary.contrastText"
                                : "text.secondary"
                            : "text.primary"
                    }
                />
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
        </>
    );
}
