import { useMemo } from "react";

import { renderMarkdown } from "@/utils/safeHtml";

const WORD_BREAK_STYLE = { wordBreak: "break-word" } as const;

interface MarkdownContentProps {
    content: string;
}

/**
 * Renders a markdown string as sanitized, `marked`-parsed HTML. Deliberately unstyled —
 * callers wrap this in their own `Box` so the markdown/pre-wrap sx applies to
 * the same container as any sibling elements (e.g. a streaming progress bar).
 *
 * The parse is memoized on `content`: while a reply streams, the whole
 * transcript re-renders every animation frame, and re-parsing every visible
 * message each time is the expensive part of that.
 */
export default function MarkdownContent({ content }: MarkdownContentProps) {
    const html = useMemo(
        () => ({ __html: renderMarkdown(content) }),
        [content],
    );

    return <div style={WORD_BREAK_STYLE} dangerouslySetInnerHTML={html} />;
}
