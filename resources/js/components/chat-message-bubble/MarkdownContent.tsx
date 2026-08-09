import { marked } from "marked";

interface MarkdownContentProps {
    content: string;
}

/**
 * Renders a markdown string as `marked`-parsed HTML. Deliberately unstyled —
 * callers wrap this in their own `Box` so the markdown/pre-wrap sx applies to
 * the same container as any sibling elements (e.g. a streaming progress bar).
 */
export default function MarkdownContent({ content }: MarkdownContentProps) {
    return (
        <div
            style={{ wordBreak: "break-word" }}
            dangerouslySetInnerHTML={{
                __html: marked.parse(content, { breaks: true }) as string,
            }}
        />
    );
}
