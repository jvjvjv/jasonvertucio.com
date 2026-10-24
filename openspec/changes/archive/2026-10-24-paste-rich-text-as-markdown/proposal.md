## Why

Job descriptions are almost always copied from a rendered web page (LinkedIn, Greenhouse, Lever, a company careers site). Pasting into a plain `<textarea>` keeps only the clipboard's `text/plain` flavor, so headings, bullet lists, bold "Requirements" labels and links collapse into undifferentiated lines. That flattened text is what the AI receives — for fit analysis, targeted resumes and company intel — and what the admin later re-reads. The same loss happens when pasting formatted content into the chat composer. Every one of these fields already *consumes* Markdown (the AI reads it, the cover letter body renders it, chat messages render it), so the structure is worth keeping.

## What Changes

- Pasting rich content (clipboard carrying `text/html`) into an opted-in textarea inserts a Markdown rendering of that content at the cursor, replacing any selection, instead of the flattened plain text.
- Converted structure: headings, paragraphs, bold/italic, ordered and unordered (nested) lists, links, blockquotes, inline code and code blocks, horizontal rules, and tables (as GFM pipe tables).
- Stripped: scripts, styles, images, comments, tracking markup and empty wrappers. Content that carries no meaningful formatting (e.g. a paste from a code editor or terminal that only wraps text in `div`/`span`) falls back to the clipboard's plain text, so code pastes are never mangled.
- Plain-text paste (clipboard with no `text/html`, or the browser's own "paste as plain text", Ctrl/Cmd+Shift+V) behaves exactly as today.
- The paste stays undoable with a single Ctrl/Cmd+Z where the browser allows it.
- Opted-in fields (those whose content is consumed as Markdown):
  - Job Description on New Session (`JobDetailsForm`) and on the Discussion page's details (`DetailsForm`)
  - The chat message composer (`ChatInputArea`), which covers every chat surface — application sessions, company intel, and the public chat bots
  - Cover letter Message Body
  - System prompt Content
  - The targeted-resume Markdown editor (`TailoredResumeEditor`, `@uiw/react-md-editor`)
- Fields that hold JSON, regex, URL patterns or short labels (e.g. `JSONConfigEditor`, `WebToolPolicyEditor`, role/permission dialogs) are deliberately **not** opted in.
- **New dependency (needs approval):** `turndown` plus `turndown-plugin-gfm` (and `@types/turndown`) for HTML → Markdown conversion.

## Capabilities

### New Capabilities

- `rich-paste-markdown`: Converting rich clipboard content to Markdown when pasted into Markdown-consuming textareas — when conversion happens, what it produces, what it strips, the plain-text fallback, undo, and which fields opt in.

### Modified Capabilities

<!-- None. chat-input-focus-resize governs the composer's height only; paste conversion does not change any of its requirements (a converted paste grows the composer exactly as any paste does). -->

## Impact

- **Frontend only.** No backend, route, schema or API change; the server keeps receiving a string.
- New: an HTML → Markdown utility and a reusable paste hook under `resources/js/` (shared by the admin and chat bundles).
- Edited: `JobDetailsForm.tsx`, `DetailsForm.tsx`, `ChatInputArea.tsx`, `cover-letters/Form.tsx`, `ai/system-prompts/Form.tsx`, `TailoredResumeEditor.tsx`.
- `package.json` / `package-lock.json`: two runtime dependencies and one type package.
- No JS test runner exists in the project, so verification is manual in the browser against representative sources (a LinkedIn/Greenhouse posting, a Google Doc, a VS Code selection, plain text).
