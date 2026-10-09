import { marked } from "marked";
import { useMemo } from "react";

const WORD_BREAK_STYLE = { wordBreak: "break-word" } as const;

interface MarkdownContentProps {
    content: string;
}

/**
 * Renders a markdown string as `marked`-parsed HTML. Deliberately unstyled —
 * callers wrap this in their own `Box` so the markdown/pre-wrap sx applies to
 * the same container as any sibling elements (e.g. a streaming progress bar).
 *
 * The parse is memoized on `content`: while a reply streams, the whole
 * transcript re-renders every animation frame, and re-parsing every visible
 * message each time is the expensive part of that.
 */
export default function MarkdownContent({ content }: MarkdownContentProps) {
    const html = useMemo(
        () => ({ __html: marked.parse(content, { breaks: true }) as string }),
        [content],
    );

    return <div style={WORD_BREAK_STYLE} dangerouslySetInnerHTML={html} />;
}
