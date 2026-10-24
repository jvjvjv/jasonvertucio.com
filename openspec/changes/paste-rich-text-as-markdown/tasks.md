## 1. Dependencies

- [ ] 1.1 Confirm with the developer that adding `turndown`, `turndown-plugin-gfm` and `@types/turndown` is approved (project rule: no dependency changes without approval); stop here if not
- [ ] 1.2 `npm install turndown turndown-plugin-gfm` and `npm install -D @types/turndown`; add a local `declare module "turndown-plugin-gfm"` typing if the package ships none; verify `npm run build` succeeds and `package-lock.json` is updated

## 2. Converter

- [ ] 2.1 Create `resources/js/utils/htmlToMarkdown.ts` with `hasMeaningfulFormatting(doc: Document): boolean` (the element allowlist from design.md, ignoring Google Docs' `font-weight:normal` `<b>` wrapper); verify with `npm run lint` and by calling it from the browser console on a VS Code clipboard sample (false) and a job-posting sample (true)
- [ ] 2.2 In the same file, export `htmlToMarkdown(html: string): string` configured per design.md (atx headings, `-` bullets, fenced code, GFM tables), removing scripts/styles/media/form controls, flattening tags with no Markdown equivalent to their text, dropping empty list items, collapsing 3+ newlines and trimming; verify in the console that headings, nested lists, links, a table and an image-plus-paragraphs sample produce the outputs required by the spec's "Formatting that is converted" and "Unsafe and non-text content is stripped" scenarios, with no raw HTML tags in any output

## 3. Paste hook

- [ ] 3.1 Create `resources/js/hooks/useMarkdownPaste.ts` returning a stable `onPaste` handler: no `text/html` → return without `preventDefault`; not formatted or empty conversion → return without `preventDefault`; otherwise `preventDefault()` and insert via `execCommand("insertText")`, falling back to a manual splice that updates the controlled value through the native setter + `input` event and restores the caret; verify with `npm run lint`

## 4. Opt in the Markdown-consuming fields

- [ ] 4.1 `resources/js/admin/pages/resume/applications/JobDetailsForm.tsx` — add `onPaste` to Job Description and mention Ctrl/Cmd+Shift+V for plain text in the placeholder; verify a job posting pasted on New Session becomes Markdown and Begin Analysis submits it
- [ ] 4.2 `resources/js/admin/pages/resume/applications/DetailsForm.tsx` — add `onPaste` to Job Description; verify a paste on the Discussion page converts and Save persists the Markdown (reload shows it)
- [ ] 4.3 `resources/js/components/ChatInputArea.tsx` — add `onPaste`; verify on an application session and a public chat bot that a formatted paste converts, sending sends the Markdown, and the composer's grow-to-cap/collapse-on-blur behavior is unchanged
- [ ] 4.4 `resources/js/admin/pages/cover-letters/Form.tsx` — add `onPaste` to Message Body and mention plain-text paste in its existing helper text; verify a formatted paste converts and the generated letter renders the formatting
- [ ] 4.5 `resources/js/admin/pages/ai/system-prompts/Form.tsx` — add `onPaste` to Content; verify a formatted paste converts and saves
- [ ] 4.6 `resources/js/admin/pages/resume/targeted/TailoredResumeEditor.tsx` — pass `onPaste` through `MDEditor`'s `textareaProps`; verify a formatted paste converts inside the editor and its preview pane renders it

## 5. Verification

- [ ] 5.1 Manual matrix in Chrome and Firefox (and Safari if available) on one opted-in field, covering each spec scenario: a real job posting (LinkedIn or Greenhouse), a Google Doc with bold/lists/table, a VS Code multi-line indented selection (plain text, indentation intact), plain text from a text editor, Ctrl/Cmd+Shift+V of a web page (no conversion), paste in the middle of text, paste over a selection, and a single Ctrl/Cmd+Z restoring the pre-paste text; record any browser where undo falls back
- [ ] 5.2 Paste formatted content into an excluded field (AI system JSON config, web tool policy editor) and confirm plain text is inserted unchanged
- [ ] 5.3 Run `npm run lint` and `npm run build` clean, and fix Prettier issues in the files touched
- [ ] 5.4 Add a short "Rich paste" note to CLAUDE.md (shared utility + hook location, the opt-in rule and the excluded field types), since it is a new cross-cutting frontend pattern
