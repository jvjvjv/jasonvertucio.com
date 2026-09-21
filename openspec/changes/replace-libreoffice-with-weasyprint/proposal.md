## Why

Every PDF the site produces is made by shelling out to `libreoffice --headless --convert-to pdf` — an entire office suite installed on the production host for the sole purpose of converting three documents. It costs **339 MB** on disk (`/usr/lib/libreoffice`; 319 MB `libreoffice-common` + 11 MB `libreoffice-writer`) and roughly **1.5 s per document**, because every call starts a fresh process.

A spike against the live resume data measured the alternative. WeasyPrint renders the same resume in **0.7 s** from a **34 MB** dependency chain, embeds all four template faces correctly (LibreOffice's output omits Montserrat Bold and pulls in OpenSymbol for bullets), and — with CSS derived by hand from the template's own `styles.xml` — placed every section heading on the same page LibreOffice did, split the two-column skills region into the same groups, and reproduced the cover letter's floating signature geometry exactly. The three risks that could have killed the approach (two-column flow, column regions overflowing a page, and the overlapping signature) were each exercised and each held.

## What Changes

- Add a **`PdfRenderer`** service that renders a PDF by invoking WeasyPrint on generated HTML, replacing the `exec('libreoffice ...')` call in all three document services.
- Add a **`StylesheetTranslator`** that reads the shared template's `styles.xml`, `sectPr` and letterhead paragraphs at render time and emits the CSS the PDF is laid out with, cached per template mtime. The DOCX template remains the single home of the design: a Word edit reaches the PDF with no command to re-run and no second stylesheet to keep in sync.
- Add a **`MarkdownToHtmlConverter`** that turns the same Markdown dialect `MarkdownToOpenXmlConverter` consumes into HTML, honouring the same section-key contract (`# Skills` → two-column flow, `# Experience` → job-title/company styling, `# Summary` suppressed) and the same `---` column marker semantics.
- Render the cover letter's signature from the existing `SignatureImageService` output, positioned with CSS absolute positioning in place of `wp:anchor`/`wrapNone`/`allowOverlap`. The luminance keying stays load-bearing — the raw source PNG renders as an opaque white box over the closing.
- Supply `@font-face` from `config('resume.fonts')` — the same static TTFs `resume:embed-fonts` writes into the template — so the PDF's faces cannot drift from the DOCX's.
- **BREAKING**: `generatePdf()` no longer requires a generated DOCX. The `DOCX file not found. Generate DOCX first.` failure is removed from all three services, and a PDF may be produced for a document that has never had a DOCX generated.
- **BREAKING**: PDF line breaking is no longer Word's. Section placement, styling and page count match the DOCX; individual line breaks within a paragraph will differ, because no engine but Word or LibreOffice reproduces Word's line-breaking exactly. Design parity is the accepted standard — the DOCX is what applicant tracking systems parse, the PDF is what people look at.
- Remove `libreoffice-writer` from the Dockerfile's `development` stage, and remove LibreOffice from the production host.
- Document the production install as `dnf` steps (the host is RHEL-family: the README's SELinux troubleshooting and the apache/supervisord layout in `CLAUDE.md`), including the SELinux context the new binary needs to be executable by apache.

## Capabilities

### New Capabilities

- `document-pdf-rendering`: How a PDF is produced for each document type — rendered from the same body source as the DOCX rather than converted from it; how the shared template's styles become the PDF's CSS; how fonts, the two-column skills region and the cover letter signature are reproduced; and how a rendering failure is reported.

### Modified Capabilities

- `document-template-rendering`: The requirement **"PDF generation continues from the generated DOCX"** no longer holds. Its two scenarios change — a PDF is no longer produced *by converting* the DOCX, and requesting a PDF with no DOCX present no longer fails. The requirement is replaced by one stating that a document's PDF and DOCX are rendered from the same body source and the same template styling, so they describe the same content without one depending on the other.

## Impact

- `app/Services/Concerns/GeneratesResumeDocuments.php` — `generatePdf()` renders instead of converting; the DOCX precondition is removed.
- `app/Services/CoverLetterDocumentService.php` — same, plus the signature's CSS placement.
- `app/Services/TargetedResumeDocumentService.php` — same, rendering from stored `tailored_data` Markdown.
- New: `app/Services/Resume/PdfRenderer.php`, `app/Services/Resume/StylesheetTranslator.php`, `app/Services/Resume/MarkdownToHtmlConverter.php`, `app/Services/Resume/HtmlDocumentComposer.php`.
- `config/resume.php` — the WeasyPrint binary path and render timeout.
- `Dockerfile` — `development` stage installs `weasyprint` instead of `libreoffice-writer`.
- Production host — `dnf` install of WeasyPrint and its dependencies, removal of LibreOffice, SELinux context for the binary. Documented in `README.md`.
- `CLAUDE.md` — the **PDF conversion** section currently describes LibreOffice as a stopgap and asks for exactly this replacement; it is rewritten to describe the rendering path.
- Tests — the three services' PDF paths, plus new unit coverage for the translator, the HTML converter and the column/signature behaviour.
- **Independent of `offload-resume-docx-generation`** (in progress, 0/14 tasks). That change moves *when* generation runs — `ResumeEditCandidateService::approve()` dispatches an event instead of generating inline; it never touches `generatePdf()`'s body, which is all this change rewrites. Their spec surfaces are disjoint: that change specs `resume-document-generation-events`, `ai-persona-resume-editing` and `resume-candidate-review-mcp-tools` and never states *how* a PDF is produced; this one specs `document-pdf-rendering` and never states *when*. No shared file is edited by both. The one substantive interaction is to that change's motivation: the 1.5 s LibreOffice call is the bulk of the latency it exists to move off the request path, and this change removes it.
