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

/** Renders the interleaved reasoning/text block sequence used by streamed and stored assistant messages. */
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

                return (
                    <Box
                        key={i}
                        sx={{
                            ...markdownSx,
                            ...preWrapSx,
                            mt: i > 0 ? 1 : 0,
                        }}
                    >
                        <MarkdownContent content={block.content} />
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
            {sentAt ? (
                <SentAtLabel sentAt={sentAt} color="text.secondary" />
            ) : null}
        </>
    );
}
