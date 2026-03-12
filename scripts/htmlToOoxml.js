import { markdownToOoxml } from './markdownToOoxml.js';

function decodeHtmlEntities(html) {
    return String(html)
        .replace(/&nbsp;/gi, ' ')
        .replace(/&amp;/gi, '&')
        .replace(/&lt;/gi, '<')
        .replace(/&gt;/gi, '>')
        .replace(/&quot;/gi, '"')
        .replace(/&#39;/gi, "'");
}

function stripRemainingTags(value) {
    return value.replace(/<[^>]+>/g, '');
}

function normalizeInlineHtml(value) {
    return value
        .replace(/<(strong|b)>(.*?)<\/(strong|b)>/gis, '**$2**')
        .replace(/<(em|i)>(.*?)<\/(em|i)>/gis, '*$2*')
        .replace(/<br\s*\/?>/gi, '  \n');
}

export function htmlToMarkdown(html) {
    if (!html || !html.trim()) {
        return '';
    }

    let markdown = decodeHtmlEntities(html);

    markdown = markdown
        .replace(/\r\n/g, '\n')
        .replace(/<h1[^>]*>(.*?)<\/h1>/gis, '\n# $1\n\n')
        .replace(/<h2[^>]*>(.*?)<\/h2>/gis, '\n## $1\n\n')
        .replace(/<h3[^>]*>(.*?)<\/h3>/gis, '\n### $1\n\n')
        .replace(/<p[^>]*>(.*?)<\/p>/gis, '\n$1\n\n')
        .replace(/<li[^>]*>(.*?)<\/li>/gis, '\n- $1')
        .replace(/<ul[^>]*>/gis, '\n')
        .replace(/<\/ul>/gis, '\n\n')
        .replace(/<ol[^>]*>/gis, '\n')
        .replace(/<\/ol>/gis, '\n\n');

    markdown = normalizeInlineHtml(markdown);
    markdown = stripRemainingTags(markdown);
    markdown = markdown
        .replace(/\n{3,}/g, '\n\n')
        .replace(/[ \t]+\n/g, '\n')
        .trim();

    return markdown;
}

export function htmlToOoxml(html) {
    return markdownToOoxml(htmlToMarkdown(html));
}
