const LIST_ITEM = /^\s*(?:[-*+]|\d+[.)])\s+/;
const HEADING = /^#{1,6}\s/;
const SPEAKABLE = /[\p{L}\p{N}]/u;

/**
 * Splits Markdown into the pieces read aloud one request at a time:
 * paragraphs, list items and headings, in order. A piece longer than
 * `maxChars` is cut at sentence ends (or, failing that, at spaces) so every
 * request stays inside the server's limit. Pieces with nothing to say, such
 * as a horizontal rule, are dropped.
 */
export default function speechBlocks(
    markdown: string,
    maxChars: number,
): string[] {
    const blocks: string[] = [];
    let current: string[] = [];

    const flush = () => {
        if (current.length > 0) {
            blocks.push(current.join("\n"));
            current = [];
        }
    };

    for (const line of markdown.split(/\r?\n/)) {
        if (line.trim() === "") {
            flush();
        } else if (HEADING.test(line)) {
            flush();
            blocks.push(line);
        } else if (LIST_ITEM.test(line)) {
            flush();
            current.push(line);
        } else {
            current.push(line);
        }
    }

    flush();

    return blocks
        .flatMap((block) => splitLong(block, maxChars))
        .filter((block) => SPEAKABLE.test(block));
}

function splitLong(block: string, maxChars: number): string[] {
    if (block.length <= maxChars) {
        return [block];
    }

    const pieces: string[] = [];
    let current = "";

    for (const sentence of block.split(/(?<=[.!?])\s+/)) {
        for (const part of hardWrap(sentence, maxChars)) {
            if (current !== "" && current.length + 1 + part.length > maxChars) {
                pieces.push(current);
                current = part;
            } else {
                current = current === "" ? part : `${current} ${part}`;
            }
        }
    }

    if (current !== "") {
        pieces.push(current);
    }

    return pieces;
}

function hardWrap(text: string, maxChars: number): string[] {
    const parts: string[] = [];
    let rest = text;

    while (rest.length > maxChars) {
        const space = rest.lastIndexOf(" ", maxChars);
        const cut = space > 0 ? space : maxChars;
        parts.push(rest.slice(0, cut));
        rest = rest.slice(cut).trimStart();
    }

    if (rest !== "") {
        parts.push(rest);
    }

    return parts;
}
