## Context

See proposal.md — Why. Every target field is an MUI `TextField` with `multiline` (which renders a `<textarea>`, auto-sized via `react-textarea-autosize` when `maxRows`/`minRows` are used) whose value is controlled React state — `useState` in `ChatInputArea`'s parent, Inertia `useForm` `setData` in the cover letter and system prompt forms, prop callbacks in `JobDetailsForm`/`DetailsForm`. The one exception is `TailoredResumeEditor`, which uses `@uiw/react-md-editor`; it exposes its inner textarea through `textareaProps`.

`marked` is already a dependency (Markdown → HTML for display). Nothing converts the other direction. The project has no JS test runner (only ESLint), so the converter cannot be unit-tested without adding tooling.

`ChatInputArea` is shared by the admin bundle and the public chat bundle (`resources/js/chat/`), so shared code belongs under `resources/js/utils` and `resources/js/hooks`, not under `resources/js/admin`.

## Goals / Non-Goals

**Goals:**
- One converter and one paste hook, applied per field with a one-line opt-in.
- Work with every existing controlled-state style without changing it.
- Keep code pastes and plain-text pastes byte-for-byte unchanged.

**Non-Goals:**
- A rich-text / WYSIWYG editor, or Markdown preview in these fields.
- Converting images (no upload pipeline; `data:` URIs would bloat the field and the AI prompt).
- Site-specific cleanup rules (e.g. stripping LinkedIn's "Show more" chrome) — the generic strip rules come first; per-site rules can follow if real pastes show a need.
- Changing how any field's value is stored or rendered server-side.

## Decisions

### HTML → Markdown with `turndown` + `turndown-plugin-gfm`

`turndown` is the de-facto browser HTML→Markdown converter (small, no dependencies, DOM-based, configurable rules); the GFM plugin adds tables, strikethrough and task lists. Configure: `headingStyle: "atx"`, `bulletListMarker: "-"`, `codeBlockStyle: "fenced"`, `emDelimiter: "*"`. Remove `script`, `style`, `noscript`, `iframe`, `img`, `svg`, `picture`, `video`, `audio`, `canvas`, `form`, `input`, `button`, `select`, `textarea`; add a rule that keeps only the text content of any element turndown would otherwise pass through as raw HTML (its default `keep`/blank behavior can emit tags). Post-process: collapse 3+ newlines to 2, trim.

*Alternatives:* `rehype-remark` / `hast-util-to-mdast` (unified pipeline) — more correct but several packages and much larger for this use. Hand-rolled DOM walker — avoids a dependency but reimplements list nesting, escaping and tables, which is where converters get hard. Adding a dependency needs the developer's approval; this is flagged in the proposal and is task 1.

### Decide "meaningful formatting" by element allowlist

Parse `text/html` with `DOMParser` (inert — scripts don't run, images don't load). Treat it as formatted only if it contains at least one of `h1`–`h6`, `ul`, `ol`, `li`, `table`, `blockquote`, `strong`, `b`, `em`, `i`, `a[href]`, `hr`, `pre`, `code`. VS Code, JetBrains and terminals emit only `div`/`span`/`br` (+ inline styles), so they fall back to plain text. Google Docs wraps everything in `<b id="docs-internal-guid-…" style="font-weight:normal">`; add a turndown rule (and an exclusion in the check) so a `b`/`strong` whose inline style sets `font-weight` to `normal`/`400` is not treated as bold.

*Alternative:* always convert when HTML is present — breaks code pastes, which are common in the chat composer.

### Insert with `execCommand("insertText")`, fall back to a manual splice

`document.execCommand("insertText", false, md)` on the focused textarea replaces the selection, moves the caret, records a native undo step, and fires a real `input` event, so React's `onChange` runs and every existing state style (useState, `setData`, prop callback) updates with no extra wiring. It is deprecated but implemented in all current browsers and is the only way to keep native undo. If it returns `false` or throws, splice manually (`value.slice(0, selectionStart) + md + value.slice(selectionEnd)`), call the field's `onChange` path with the new value via the native value setter + dispatched `input` event, and restore the caret with `setSelectionRange` after the render; undo is then best-effort, which the spec allows ("in browsers that support…").

*Alternative:* always splice into React state — loses undo everywhere and needs a per-field setter passed into the hook.

### A `useMarkdownPaste()` hook returning an `onPaste` handler

`resources/js/utils/htmlToMarkdown.ts` exports `htmlToMarkdown(html): string` and `hasMeaningfulFormatting(doc): boolean`. `resources/js/hooks/useMarkdownPaste.ts` returns a stable `onPaste(e: ClipboardEvent<HTMLTextAreaElement | HTMLInputElement | HTMLDivElement>)`: read `clipboardData.getData("text/html")`; if empty, return without `preventDefault` (native paste untouched); if not formatted or the conversion is empty, return likewise; otherwise `preventDefault()` and insert. The handler resolves the textarea from `e.target`, so it works when attached to the `TextField` (React's paste event bubbles from the textarea) or to `slotProps.htmlInput`. Each opted-in field adds `onPaste={onMarkdownPaste}`; `TailoredResumeEditor` passes it via `textareaProps`.

Opt-in per field rather than a global listener or a wrapped `TextField`: the set of Markdown-consuming fields is small and explicit, and a global listener would silently convert the JSON/regex fields the spec excludes.

### Converter is imported statically, not lazily

Importing `turndown` dynamically inside the handler would make the paste async, but `preventDefault` must be called synchronously. Import it statically in the hook module; it is ~10 kB gzipped and the opted-in pages are already their own lazy chunks (`resolvePageComponent` with a non-eager glob). The chat bundle takes the cost once, via `ChatInputArea`.

## Risks / Trade-offs

- [`execCommand` is deprecated and could be removed] → The manual-splice fallback already exists and is exercised whenever `execCommand` returns `false`; removal degrades undo only.
- [Converter output on messy real-world HTML (LinkedIn, Workday) contains noise — stray empty bullets, `\` escapes on characters like `*` or `_` in plain prose] → Collapse blank lines, drop empty list items, and keep turndown's escaping (it prevents accidental formatting). Manual verification against real postings is a task; site-specific rules are a non-goal for now.
- [A user wants the flattened text] → Ctrl/Cmd+Shift+V pastes plain text natively; mention it in the field's helper text where helper text already exists (Job Description placeholder, cover letter helper).
- [No automated tests for the converter] → Accept for now (adding Vitest is a dependency/tooling decision beyond this change); the manual verification matrix in tasks.md covers each spec scenario. Offer Vitest as a follow-up.
- [MUI `TextField` with `maxRows` uses a hidden shadow textarea for measuring] → `e.target` of a real paste is always the visible textarea; the shadow is `aria-hidden` and never focused.

## Migration Plan

Frontend-only; ships with a normal `npm ci && npm run build`. Rollback is reverting the commit. No data migration — previously stored plain-text descriptions are untouched.
