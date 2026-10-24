import { useCallback } from "react";

import type { ClipboardEvent } from "react";

import {
    documentToMarkdown,
    hasMeaningfulFormatting,
} from "@/utils/htmlToMarkdown";

type TextControl = HTMLTextAreaElement | HTMLInputElement;

const isTextControl = (target: EventTarget): target is TextControl =>
    target instanceof HTMLTextAreaElement || target instanceof HTMLInputElement;

/**
 * Replaces the control's selection with `text` without `execCommand`: sets the
 * value through the prototype's native setter so React's value tracker sees a
 * change, dispatches `input` so the field's `onChange` runs, then restores the
 * caret. Undo is not recorded on this path.
 */
const spliceInto = (control: TextControl, text: string): void => {
    const start = control.selectionStart ?? control.value.length;
    const end = control.selectionEnd ?? start;
    const next =
        control.value.slice(0, start) + text + control.value.slice(end);
    const caret = start + text.length;
    const prototype =
        control instanceof HTMLTextAreaElement
            ? HTMLTextAreaElement.prototype
            : HTMLInputElement.prototype;

    Object.getOwnPropertyDescriptor(prototype, "value")?.set?.call(
        control,
        next,
    );
    control.dispatchEvent(new Event("input", { bubbles: true }));
    control.setSelectionRange(caret, caret);
    requestAnimationFrame(() => {
        control.setSelectionRange(caret, caret);
    });
};

/**
 * Inserts at the caret the way a native paste does. `insertText` is deprecated
 * but still the only API that records a native undo step and fires a real
 * `input` event, so every controlled-state style updates unaided.
 */
const execInsertText = (text: string): boolean => {
    try {
        // eslint-disable-next-line @typescript-eslint/no-deprecated -- the only API that keeps native undo; see above.
        return document.execCommand("insertText", false, text);
    } catch {
        return false;
    }
};

const insertText = (control: TextControl, text: string): void => {
    control.focus();

    if (!execInsertText(text)) {
        spliceInto(control, text);
    }
};

/**
 * The Markdown for clipboard HTML, or an empty string when the paste should be
 * left to the browser: no meaningful formatting, or the conversion failed.
 */
const convert = (html: string): string => {
    try {
        const doc = new DOMParser().parseFromString(html, "text/html");

        return hasMeaningfulFormatting(doc) ? documentToMarkdown(doc) : "";
    } catch {
        return "";
    }
};

/**
 * An `onPaste` handler for textareas whose content is consumed as Markdown.
 * A paste carrying formatted HTML is inserted as Markdown; anything else —
 * plain text, code-editor HTML, an empty conversion, an error — is left to the
 * browser's native paste. Opt fields in one by one: JSON, regex and credential
 * fields must not get it.
 */
export default function useMarkdownPaste(): (
    event: ClipboardEvent<HTMLElement>,
) => void {
    return useCallback((event: ClipboardEvent<HTMLElement>) => {
        if (!isTextControl(event.target)) {
            return;
        }

        const html = event.clipboardData.getData("text/html");

        if (!html) {
            return;
        }

        const markdown = convert(html);

        if (!markdown) {
            return;
        }

        event.preventDefault();
        insertText(event.target, markdown);
    }, []);
}
