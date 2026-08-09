import Box from "@mui/material/Box";
import LinearProgress from "@mui/material/LinearProgress";

import { markdownSx } from "../../admin/utils/markdownSx";
import ReasoningPanel from "../ReasoningPanel";

import MarkdownContent from "./MarkdownContent";
import SentAtLabel from "./SentAtLabel";

import type { MessageBlock } from "../ChatMessageBubble";
import type { SystemStyleObject, Theme } from "@mui/system";

interface BlockContentProps {
    blocks: MessageBlock[];
    isStreaming: boolean;
    isAuthenticated: boolean;
    activeBlockType: "text" | "reasoning" | null;
    sentAt: string | null | undefined;
    preWrapSx: SystemStyleObject<Theme>;
}

/**
 * Renders the interleaved reasoning/text block sequence used by streamed and
 * stored assistant messages.
 *
 * The streaming progress bar is owned here rather than by the individual
 * panels: a reasoning panel only renders its bar while expanded, so a collapsed
 * one — or a hidden one, for guests — left the turn with no feedback at all.
 * One bar at the end of the message body always shows, whatever is collapsed.
 */
export default function BlockContent({
    blocks,
    isStreaming,
    isAuthenticated,
    activeBlockType,
    sentAt,
    preWrapSx,
}: BlockContentProps) {
    return (
        <>
            {blocks.map((block, i) => {
                const isLastBlock = i === blocks.length - 1;
                const isActiveBlock = isStreaming && isLastBlock;

                if (block.type === "reasoning") {
                    return (
                        isAuthenticated && (
                            <ReasoningPanel
                                key={`${block.type}-${i}`}
                                content={block.content}
                                isActive={
                                    isActiveBlock &&
                                    activeBlockType === "reasoning"
                                }
                            />
                        )
                    );
                }

                return (
                    <Box
                        key={`${block.type}-${i}`}
                        sx={{
                            ...markdownSx,
                            ...preWrapSx,
                            mt: i > 0 ? 1 : 0,
                        }}
                    >
                        <MarkdownContent content={block.content} />
                    </Box>
                );
            })}
            {sentAt ? (
                <SentAtLabel sentAt={sentAt} color="text.secondary" />
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
        </>
    );
}
