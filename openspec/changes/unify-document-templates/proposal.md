## Why

The site generates three Word documents — the main resume, targeted resumes, and cover letters — from three separate DOCX templates (`2026 resume template.docx`, `2026 targeted resume template.docx`, `2026 cover letter template.docx`) through three unrelated code paths. A branding change means editing three files in Word and verifying three rendering pipelines; the three documents already drift (different header parts, different fonts, one carries a logo image the others don't). Consolidating onto the single header-only `resources/resume/2026 template.docx` makes the visual identity editable in one place and collapses two of the three rendering strategies into one.

## What Changes

- **BREAKING**: All three document types render from `resources/resume/2026 template.docx`. `2026 resume template.docx`, `2026 targeted resume template.docx`, and `2026 cover letter template.docx` are retired.
- **BREAKING**: The main resume DOCX is no longer produced by `docxtemplater` loops (`{#skills}` / `{#experience}` / `{#projects}` / `{#education}`) bound to `getDocxData()`. The shared template has no body — only the header tags `{name}`, `{title}`, `{email}`, `{phone}`, `{url}`. The resume body is instead composed as Markdown from structured resume data and converted to OOXML by `MarkdownToOpenXmlConverter`, which is how targeted resumes already render.
- **BREAKING**: Cover letters lose the header part (`word/header1.xml`) and logo image carried by the old cover letter template. The shared template's presentation applies instead. The letter's structure — date, company address block, greeting, message body, closing, signature — is reproduced as generated OOXML appended to the shared template rather than bound to template placeholders.
- A new `SignatureImageService` embeds `resources/resume/signature.png` at the end of cover letters only, scaled to **2 inches tall** with width kept proportional (≈0.96″ at the current 199×415 px source), and recolors its non-transparent pixels to the brand blue `#1B587C` (`--color-primary` in `resources/css/resume.css`, and the heading color already used in the retired resume template).
- `config/resume.php`'s `template` key becomes the single source of truth for the shared template path; `TargetedResumeDocumentService` and `CoverLetterDocumentService` stop hardcoding their own `base_path(...)` template paths and read the same config value.
- `scripts/generate-resume.js` is retired along with the templating strategy it implements. `scripts/generate-cover-letter.js` is retired too, since cover letters no longer bind `{@messageBody}` / `{date}` / `{greeting}` / `{closing}` / `{signature}` placeholders through `docxtemplater`; both document types move to the PHP `ZipArchive` + `MarkdownToOpenXmlConverter` path that `TargetedResumeDocumentService` already uses. `scripts/markdownToOoxml.js` loses its only caller.
- The resume/targeted-resume/cover-letter PDF paths are unchanged — LibreOffice still converts the generated DOCX.

## Capabilities

### New Capabilities

- `document-template-rendering`: Defines the single shared DOCX template contract — which file is authoritative, the header placeholder set it must expose, how each of the three document types composes its body onto it, and what happens when the template is missing or malformed.
- `cover-letter-signature-image`: Defines the signature image embedded at the end of cover letters — its source file, the 2-inch-tall proportional sizing rule, the brand-blue recoloring, that it appears on cover letters only, and the behavior when the source image is missing or unreadable.

### Modified Capabilities

- `targeted-resume-manual-editing`: The "Saving a manual edit persists content and regenerates artifacts" requirement refers to "the existing document-generation path". That path's template source changes from the hardcoded `2026 targeted resume template.docx` to the shared configured template. The requirement's observable behavior (save persists, artifacts regenerate, failure is surfaced) is unchanged, but the spec's description of the generation path needs to stop naming a retired template.

## Impact

- `config/resume.php` — `template` repointed to `resources/resume/2026 template.docx` and documented as shared across all three document types.
- `app/Services/Concerns/GeneratesResumeDocuments.php` — `generateDocx()` stops shelling out to Node and composes the resume body through the shared OOXML renderer; `$scriptPath` is removed.
- `app/Services/TargetedResumeDocumentService.php` — template path comes from config instead of `base_path()`; body-composition logic is extracted to a shared renderer.
- `app/Services/CoverLetterDocumentService.php` — rewritten to use the shared renderer and `ZipArchive` instead of `shell_exec`-ing `generate-cover-letter.js`; `prepareTemplateForDocxtemplater()` / `normalizeSplitPlaceholders()` fold into the shared renderer's split-run handling.
- New: a shared OOXML document renderer (extracted from `TargetedResumeDocumentService`'s `replaceSimplePlaceholders()` / `replaceSplitPlaceholderRuns()` / `appendResumeContent()`), a resume-data→Markdown composer, and `SignatureImageService`.
- `app/Services/Resume/MarkdownToOpenXmlConverter.php` — gains image-embedding support (or a companion class does) for the signature run, plus whatever style IDs the resume body needs that the targeted-resume body doesn't already exercise.
- Deleted: `scripts/generate-resume.js`, `scripts/generate-cover-letter.js`, `scripts/markdownToOoxml.js`, `resources/resume/2026 resume template.docx`, `resources/resume/2026 targeted resume template.docx`, `resources/resume/2026 cover letter template.docx`.
- `resources/resume/2026 template.docx` must gain a `word/media/` entry and the relationship/content-type plumbing for the signature PNG, or the renderer must add them at generation time.
- `tests/Feature/Services/TargetedResumeDocumentServiceTest.php` — its hardcoded `2026 targeted resume template.docx` path must move to the shared template.
- `package.json` — `docxtemplater`, `pizzip`, and the expressions parser lose their only consumers; whether to drop the dependencies is a design decision, not assumed here.
- Stale Word lock files `resources/resume/~$26 cover letter template.docx` and `~$26 template.docx` should be removed and `~$*.docx` gitignored.
- `CLAUDE.md` — the "DOCX Generation Flow" section describes `ResumeVersionService` calling a Node.js script via `shell_exec()`; that is no longer how any document is generated.
