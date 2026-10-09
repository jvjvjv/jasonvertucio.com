import DOMPurify from "dompurify";
import { marked } from "marked";

/**
 * Strips anything executable (scripts, event-handler attributes,
 * `javascript:` URLs) from an HTML string. Every string handed to
 * `dangerouslySetInnerHTML` must pass through here: the Markdown these pages
 * render comes from models and scraped job postings, not from the developer.
 */
export const sanitizeHtml = (html: string): string => DOMPurify.sanitize(html);

/**
 * Parses Markdown with `marked` and sanitizes the result. `marked` passes raw
 * HTML in its input straight through, so its output is never safe on its own.
 */
export const renderMarkdown = (markdown: string): string =>
    sanitizeHtml(marked.parse(markdown, { breaks: true, async: false }));
