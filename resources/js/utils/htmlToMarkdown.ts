import { gfm } from "@joplin/turndown-plugin-gfm";
import TurndownService from "turndown";

/**
 * Elements whose presence means the clipboard HTML carries structure worth
 * keeping. Code editors and terminals emit only `div`/`span`/`br` with inline
 * styles, so they never match and fall back to plain text.
 */
const FORMATTING_SELECTOR = [
    "h1",
    "h2",
    "h3",
    "h4",
    "h5",
    "h6",
    "ul",
    "ol",
    "li",
    "table",
    "blockquote",
    "strong",
    "b",
    "em",
    "i",
    "a[href]",
    "hr",
    "pre",
    "code",
].join(",");

const CODE_TAGS = new Set(["PRE", "CODE"]);

/**
 * Removed before conversion. Done on the DOM rather than with turndown's
 * `remove()`, which loses to built-in rules (its `image` rule would still
 * emit `![](…)`). Checkbox inputs stay so GFM task lists survive; other inputs
 * have no content and produce nothing.
 */
const STRIPPED_SELECTOR = [
    "script",
    "style",
    "noscript",
    "template",
    "iframe",
    "object",
    "embed",
    "img",
    "svg",
    "picture",
    "video",
    "audio",
    "canvas",
    "button",
    "select",
    "textarea",
    'input:not([type="checkbox"])',
].join(",");

/** Tags inside a table that the GFM plugin cannot express as a pipe table. */
const COMPLEX_TABLE_SELECTOR =
    "ul, ol, h1, h2, h3, h4, h5, h6, hr, blockquote, code, pre, table table";

const SAFE_LINK = /^(https?:|mailto:|tel:)/i;

/**
 * Google Docs wraps the whole selection in
 * `<b id="docs-internal-guid-…" style="font-weight:normal">`, which is not bold.
 */
const isNotActuallyBold = (element: Element): boolean => {
    const weight = (element as HTMLElement).style.fontWeight;

    return weight === "normal" || weight === "400";
};

/**
 * True when the parsed clipboard HTML contains at least one element with a
 * Markdown equivalent. `pre`/`code` only count when there is text outside
 * them, so a paste that is nothing but a code block stays plain text.
 */
export function hasMeaningfulFormatting(doc: Document): boolean {
    const matches = Array.from(
        doc.body.querySelectorAll(FORMATTING_SELECTOR),
    ).filter(
        (element) =>
            !(
                (element.tagName === "B" || element.tagName === "STRONG") &&
                isNotActuallyBold(element)
            ),
    );

    if (matches.some((element) => !CODE_TAGS.has(element.tagName))) {
        return true;
    }

    if (matches.length === 0) {
        return false;
    }

    const outsideCode = doc.body.cloneNode(true) as HTMLElement;
    outsideCode.querySelectorAll("pre, code").forEach((element) => {
        element.remove();
    });

    return outsideCode.textContent.trim() !== "";
}

/**
 * Tables holding lists, headings or code have no pipe-table form, and the GFM
 * plugin would emit them as raw HTML. Replace each with its cells' contents as
 * consecutive blocks so only Markdown comes out.
 */
const flattenComplexTables = (root: HTMLElement): void => {
    Array.from(root.querySelectorAll("table"))
        .filter((table) => table.querySelector(COMPLEX_TABLE_SELECTOR))
        .forEach((table) => {
            const replacement = root.ownerDocument.createElement("div");

            table.querySelectorAll("th, td").forEach((cell) => {
                const block = root.ownerDocument.createElement("div");
                block.append(...Array.from(cell.childNodes));
                replacement.append(block);
            });

            table.replaceWith(replacement);
        });
};

const isStyledBold = (node: HTMLElement): boolean => {
    const weight = node.style.fontWeight;

    return weight === "bold" || Number(weight) >= 600;
};

/** Turndown marks each node it visits; the flag is missing from its types. */
const isBlock = (node: HTMLElement): boolean =>
    (node as HTMLElement & { isBlock?: boolean }).isBlock === true;

const createService = (): TurndownService => {
    const service = new TurndownService({
        headingStyle: "atx",
        bulletListMarker: "-",
        codeBlockStyle: "fenced",
        emDelimiter: "*",
        hr: "---",
        // Anything turndown would keep verbatim is reduced to its text, so no
        // raw tag ever reaches the field.
        keepReplacement: (content, node) =>
            isBlock(node) ? `\n\n${content}\n\n` : content,
    });

    service.use(gfm);

    // Rules added later take precedence over the built-in ones.
    service.addRule("notActuallyBold", {
        filter: (node) =>
            (node.nodeName === "B" || node.nodeName === "STRONG") &&
            isNotActuallyBold(node),
        replacement: (content) => content,
    });

    // Google Docs expresses bold and italic as inline styles on spans.
    service.addRule("styledSpan", {
        filter: (node) =>
            node.nodeName === "SPAN" &&
            (isStyledBold(node) || node.style.fontStyle === "italic"),
        replacement: (content, node) => {
            if (!content.trim()) {
                return content;
            }

            const bold = isStyledBold(node) ? "**" : "";
            const italic = node.style.fontStyle === "italic" ? "*" : "";

            return `${bold}${italic}${content}${italic}${bold}`;
        },
    });

    // Turndown pads bullets to four columns (`-   item`); a single space reads
    // better and is what a person typing Markdown writes.
    service.addRule("listItem", {
        filter: "li",
        replacement: (content, node) => {
            const parent = node.parentNode as HTMLElement;
            let prefix = "- ";

            if (parent.nodeName === "OL") {
                const start = Number(parent.getAttribute("start") ?? 1);
                prefix = `${start + Array.from(parent.children).indexOf(node)}. `;
            }

            const body = content
                .replace(/^\n+/, "")
                .replace(/\n+$/, "\n")
                .replace(/\n(?=.)/g, `\n${" ".repeat(prefix.length)}`);

            return (
                prefix +
                body +
                (node.nextSibling && !body.endsWith("\n") ? "\n" : "")
            );
        },
    });

    service.addRule("unsafeLink", {
        filter: (node) =>
            node.nodeName === "A" &&
            !SAFE_LINK.test(node.getAttribute("href") ?? ""),
        replacement: (content) => content,
    });

    service.addRule("emptyListItem", {
        filter: (node) =>
            node.nodeName === "LI" && node.textContent.trim() === "",
        replacement: () => "",
    });

    return service;
};

let service: TurndownService | null = null;

/**
 * Converts clipboard HTML to Markdown: atx headings, `-` bullets, fenced code
 * and GFM pipe tables, with scripts, styles, media and form controls removed.
 * Parsing goes through `DOMParser`, which is inert — scripts do not run and
 * images do not load.
 */
export function htmlToMarkdown(html: string): string {
    const doc = new DOMParser().parseFromString(html, "text/html");

    return documentToMarkdown(doc);
}

/** {@link htmlToMarkdown} for an already-parsed document. */
export function documentToMarkdown(doc: Document): string {
    doc.body.querySelectorAll(STRIPPED_SELECTOR).forEach((element) => {
        element.remove();
    });
    flattenComplexTables(doc.body);

    service ??= createService();

    return service
        .turndown(doc.body)
        .replace(/^[ \t]+$/gm, "")
        .replace(/\n{3,}/g, "\n\n")
        .trim();
}
