/**
 * markdownToOoxml.js
 *
 * Converts a Markdown string to an OOXML (Word XML) string suitable for
 * injection via docxtemplater's rawxml module ({@tag} syntax).
 *
 * Supported Markdown:
 *   - Paragraphs (blank-line separated)
 *   - Bold (**text** or __text__)
 *   - Italic (*text* or _text_)
 *   - Bold+italic (***text***)
 *   - Unordered lists (-, *, +)
 *   - Ordered lists (1. 2. 3.)
 *   - Hard line breaks (two trailing spaces or \)
 *   - Horizontal rules (---, ***, ___)
 *   - Blockquotes (> text)
 *   - Inline code (`code`) — rendered as monospace
 *
 * Output is a series of <w:p> elements with <w:r> runs inside.
 * Inject into a docx template using {@fieldName} in the template.
 */

import { Lexer, marked } from 'marked';

const DEFAULT_FONT_SIZE_HALF_POINTS = 24;

// ─── XML helpers ────────────────────────────────────────────────────────────

function escapeXml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&apos;');
}

function wRpr(opts = {}) {
    const parts = [];
    if (!opts.mono) {
        parts.push(`<w:sz w:val="${DEFAULT_FONT_SIZE_HALF_POINTS}"/>`);
        parts.push(`<w:szCs w:val="${DEFAULT_FONT_SIZE_HALF_POINTS}"/>`);
    }
    if (opts.bold) { parts.push('<w:b/>'); }
    if (opts.italic) { parts.push('<w:i/>'); }
    if (opts.strike) { parts.push('<w:strike/>'); }
    if (opts.mono) { parts.push('<w:rFonts w:ascii="Courier New" w:hAnsi="Courier New"/>'); parts.push('<w:sz w:val="20"/>'); }
    if (opts.color) { parts.push(`<w:color w:val="${opts.color}"/>`); }
    if (parts.length === 0) { return ''; }
    return `<w:rPr>${parts.join('')}</w:rPr>`;
}

function wRun(text, opts = {}) {
    const rpr = wRpr(opts);
    const escaped = escapeXml(text);
    // Preserve leading/trailing spaces
    const space = (text.startsWith(' ') || text.endsWith(' ')) ? ' xml:space="preserve"' : '';
    return `<w:r>${rpr}<w:t${space}>${escaped}</w:t></w:r>`;
}

function wBr() {
    return `<w:r><w:br/></w:r>`;
}

function wParagraph(innerXml, pPrXml = '') {
    return `<w:p>${pPrXml}${innerXml}</w:p>`;
}

function wListPPr(numId, ilvl = 0) {
    return `<w:pPr><w:numPr><w:ilvl w:val="${ilvl}"/><w:numId w:val="${numId}"/></w:numPr></w:pPr>`;
}

// ─── Inline token → OOXML ───────────────────────────────────────────────────

/**
 * Recursively convert an array of marked inline tokens to OOXML run strings.
 */
function inlineTokensToOoxml(tokens, opts = {}) {
    let xml = '';
    for (const token of tokens) {
        switch (token.type) {
            case 'text':
                // Split on hard line breaks (two spaces + newline) or \n
                {
                    const lines = token.text.split(/\n/);
                    lines.forEach((line, i) => {
                        if (line) { xml += wRun(line, opts); }
                        if (i < lines.length - 1) { xml += wBr(); }
                    });
                }
                break;

            case 'strong':
                xml += inlineTokensToOoxml(token.tokens, { ...opts, bold: true });
                break;

            case 'em':
                xml += inlineTokensToOoxml(token.tokens, { ...opts, italic: true });
                break;

            case 'del':
                xml += inlineTokensToOoxml(token.tokens, { ...opts, strike: true });
                break;

            case 'codespan':
                xml += wRun(token.text, { ...opts, mono: true });
                break;

            case 'link':
                // Render link text as underlined blue runs (no actual hyperlink in raw XML for simplicity)
                xml += inlineTokensToOoxml(token.tokens, { ...opts, color: '0563C1' });
                break;

            case 'br':
                xml += wBr();
                break;

            case 'html':
                // Strip HTML tags, render plain text
                xml += wRun(token.text.replace(/<[^>]+>/g, ''), opts);
                break;

            case 'space':
                xml += wRun(' ', opts);
                break;

            default:
                // Fallback: render raw text if available
                if (token.raw) {
                    xml += wRun(token.raw, opts);
                }
                break;
        }
    }
    return xml;
}

// ─── Block token → OOXML ────────────────────────────────────────────────────

/**
 * Convert a list item's tokens (which may include nested lists) to paragraphs.
 * numId: the abstract list numbering ID to use
 * ilvl: current indent level (0-based)
 */
function listItemToOoxml(item, numId, ilvl) {
    let xml = '';
    let inlineXml = '';
    let hasBlock = false;

    for (const token of item.tokens) {
        if (token.type === 'text') {
            // Inline content of list item
            const inlineTokens = token.tokens ?? [{ type: 'text', text: token.text, raw: token.raw }];
            inlineXml += inlineTokensToOoxml(inlineTokens);
        } else if (token.type === 'list') {
            // Nested list — flush any inline content first as a list item paragraph
            if (inlineXml) {
                xml += wParagraph(inlineXml, wListPPr(numId, ilvl));
                inlineXml = '';
            }
            // Recurse for nested list items, bumping the numId by 10 for ordered vs unordered
            const nestedNumId = token.ordered ? numId + 10 : numId + 1;
            xml += blockTokensToOoxml(token.items.map(i => ({ ...i, _numId: nestedNumId, _ilvl: ilvl + 1 })), { nestedList: true, numId: nestedNumId, ilvl: ilvl + 1 });
            hasBlock = true;
        } else if (token.type === 'paragraph') {
            if (inlineXml) {
                xml += wParagraph(inlineXml, wListPPr(numId, ilvl));
                inlineXml = '';
            }
            const runs = inlineTokensToOoxml(token.tokens ?? []);
            xml += wParagraph(runs, wListPPr(numId, ilvl));
            hasBlock = true;
        }
    }

    // Flush remaining inline content
    if (inlineXml) {
        xml += wParagraph(inlineXml, wListPPr(numId, ilvl));
    }

    return xml;
}

/**
 * Convert block-level tokens to OOXML paragraphs.
 * opts.numId / opts.ilvl used when called recursively for nested lists.
 */
function blockTokensToOoxml(tokens, opts = {}) {
    let xml = '';
    // Track list numbering: unordered=1, ordered=2 (docxtemplater uses template's numIds)
    // We use numId=1 for bullet, numId=2 for numbered — these must exist in the template.
    const BULLET_NUM_ID = 1;
    const ORDERED_NUM_ID = 2;

    for (const token of tokens) {
        switch (token.type) {
            case 'paragraph':
                xml += wParagraph(inlineTokensToOoxml(token.tokens));
                break;

            case 'heading':
                // Render headings as bold paragraphs — no heading styles assumed in template
                xml += wParagraph(inlineTokensToOoxml(token.tokens, { bold: true }));
                break;

            case 'blockquote':
                // Render blockquote body with a grey color
                for (const inner of token.tokens) {
                    if (inner.type === 'paragraph') {
                        xml += wParagraph(inlineTokensToOoxml(inner.tokens, { color: '6B7280' }));
                    }
                }
                break;

            case 'list': {
                const numId = token.ordered ? ORDERED_NUM_ID : BULLET_NUM_ID;
                const ilvl = opts.ilvl ?? 0;
                for (const item of token.items) {
                    xml += listItemToOoxml(item, numId, ilvl);
                }
                break;
            }

            case 'code':
                // Code block: each line as a monospace paragraph
                for (const line of token.text.split('\n')) {
                    xml += wParagraph(wRun(line || ' ', { mono: true }));
                }
                break;

            case 'hr':
                // Horizontal rule: empty paragraph with a bottom border
                xml += `<w:p><w:pPr><w:pBdr><w:bottom w:val="single" w:sz="4" w:space="1" w:color="E5E7EB"/></w:pBdr></w:pPr></w:p>`;
                break;

            case 'space':
                // Extra blank lines between blocks — skip, paragraphs already provide spacing
                break;

            case 'html':
                // Strip HTML, render as plain paragraph
                {
                    const plain = token.text.replace(/<[^>]+>/g, '').trim();
                    if (plain) { xml += wParagraph(wRun(plain)); }
                }
                break;

            default:
                break;
        }
    }

    return xml;
}

// ─── Public API ─────────────────────────────────────────────────────────────

/**
 * Convert a Markdown string to an OOXML string of <w:p> elements.
 *
 * @param {string} markdown
 * @returns {string} OOXML XML string for use with docxtemplater {@tag}
 */
export function markdownToOoxml(markdown) {
    if (!markdown || !markdown.trim()) {
        return '<w:p><w:r><w:t></w:t></w:r></w:p>';
    }

    const lexer = new Lexer({ gfm: true, breaks: false });
    const tokens = lexer.lex(markdown);
    return blockTokensToOoxml(tokens);
}
