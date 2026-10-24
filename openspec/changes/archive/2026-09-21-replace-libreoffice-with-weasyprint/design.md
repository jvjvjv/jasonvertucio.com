## Context

See `proposal.md` — Why, for the motivation and the spike measurements.

The constraints that shape the approach:

- **One template is the design.** `unify-document-templates` (archived 2026-09-20) collapsed three templates and three renderers into `config('resume.template')` plus `DocumentRenderer`. Any PDF path that reintroduces a second place to edit the design gives that back.
- **Production is not Docker.** `CLAUDE.md` describes the live host as apache + supervisord at `/var/www/jasonvertucio.com`, and the README's SELinux troubleshooting puts it in the RHEL family. Whatever runs must install with `dnf` on a bare host, and be executable by the `apache` user under SELinux.
- **The body already has a common source.** `MarkdownToOpenXmlConverter` consumes one Markdown dialect for the main resume (via `ResumeMarkdownComposer`) and targeted resumes (from stored `tailored_data`); `CoverLetterBodyComposer` runs its `message_body` through the same converter. Its `parseLines()` stage (line 259) already produces a structured line array before any OOXML is emitted.
- **Fonts are already pinned in the repo.** `resume:embed-fonts` writes the static faces from `config('resume.fonts')` into the template's font parts, refusing variable faces. The TTFs on disk are by construction the same faces the DOCX embeds.
- **The template body is two paragraphs.** `word/document.xml` holds only `{name}` styled `Title` and `{title} • {email} • {phone} • {url}` styled `Header`, plus one empty `Normal`. The letterhead is structure plus styling, not a fixed image.

## Goals / Non-Goals

**Goals:**

- One rendering pipeline per document type that produces DOCX and PDF from the same parsed body, so the two cannot describe different documents.
- The shared DOCX template remains the only file anyone edits to change how the documents look, for both formats.
- A production install that is `dnf`-installable, SELinux-clean, and smaller than what it replaces.

**Non-Goals:**

- Reproducing Word's line breaking. See the spec's *"Line breaking may differ between the two formats"*.
- Changing the DOCX pipeline. `DocumentRenderer`, `MarkdownToOpenXmlConverter`'s emit stage, `InlineImageBuilder` and `FontEmbedder` are untouched except where the parse stage is extracted from under them.
- Changing *when* generation runs. That is `offload-resume-docx-generation`'s scope.
- HTML/PDF output for the site's `/resume` page. Its `@media print` block deliberately hides the resume and shows a "visit the site to download" message; that stays as it is, and the PDF path does not read those Blade views.

## Decisions

### Render both formats from one parse, rather than converting the DOCX

Extract `MarkdownToOpenXmlConverter::parseLines()` and its section vocabulary — `SECTION_ALIASES`, `SECTION_LABELS`, `SUPPRESSED_HEADINGS`, the `---` column marker and the skills-section detection — into a shared `MarkdownDocumentParser` returning a structured document model. `MarkdownToOpenXmlConverter` then emits OOXML from that model, and a new `MarkdownToHtmlConverter` emits HTML from the same model.

*Why:* the spec requires the PDF and the DOCX to contain the same sections in the same order. Two independent parsers would make that a thing to test for rather than a thing that is true by construction; a shared parse makes section aliasing, heading suppression and the column marker impossible to diverge on. It also means `CLAUDE.md`'s "the section headings are a contract" statement stays true for exactly one implementation of that contract.

*Alternative considered:* let the HTML converter re-parse the Markdown independently. Rejected — it doubles the surface where a hand-edited targeted resume reading `# Professional Experience` could style correctly in one format and not the other.

### Translate `styles.xml` into CSS at render time

A `StylesheetTranslator` opens the template, reads `docDefaults`, the named styles it recognizes, and the body `sectPr`, resolves each style's `basedOn` chain, and emits CSS. It caches the result keyed on the template's path and mtime, so a Word save invalidates it and nothing else has to.

Unit conversion is mechanical: `w:sz` is half-points (`sz/2` pt), `w:spacing`/`w:ind` are twentieths of a point (`/20` pt), `w:pgSz`/`w:pgMar` are twips (`/1440` in). `w:caps` becomes `text-transform: uppercase`, `w:pBdr/w:bottom` a `border-bottom`, `w:keepNext` a `break-after: avoid`, the `sectPr` a `@page` rule.

*Why:* it is the only option of the three offered that keeps the template the single home of the design with no step to forget. The spike proved the numbers transfer — CSS written once from these same fields, with no iteration against the LibreOffice output, put every section heading on the same page.

*Alternatives considered:* a committed CSS file built by an artisan command (cheaper at render time, but a forgotten re-run after a Word save silently drifts the PDF); a hand-maintained stylesheet (simplest, but reinstates the duplication this codebase just removed).

*Scope boundary:* the translator recognizes the styles the template actually uses — `Normal`, `Title`, `Header`, `Heading1`, `Heading2`, `Heading3`, `JobTitle`, `CompanyInfo`, `ListParagraph`, `KeyTechnologies`. A style it does not recognize inherits from its `basedOn` ancestor and is logged, rather than failing the render.

### Compose the letterhead from the template's own body

`HtmlDocumentComposer` reads the template's `document.xml` body paragraphs, applies the same placeholder substitution `DocumentRenderer` performs — including the split-run reassembly that `{url}` needs — and emits them as the PDF's letterhead, styled by the translated CSS. The document body from `MarkdownToHtmlConverter` follows.

*Why:* the spec requires the PDF's letterhead to carry the same text, styling and substitutions as the DOCX's. Reading the same two paragraphs from the same file is how that stays true when the letterhead is edited.

### Invoke WeasyPrint as a subprocess

`PdfRenderer` writes the composed HTML to a temporary file and runs the `weasyprint` binary on it via Symfony's `Process`, with `-q` (the CLI emits a FontConfig warning on every run that is noise here, since every face is supplied by `@font-face`) and a configured timeout. The binary path and timeout come from `config/resume.php`, on the same rule as `template`, `signature` and `fonts`: no service hardcodes its own.

*Why:* it is the same shape as the code it replaces, so the three call sites change mechanically. At 0.7 s per document it is already faster than what it replaces; a persistent renderer would save the ~0.4 s interpreter start but adds a process to supervise, and `offload-resume-docx-generation` is separately moving this work off the request path anyway.

*Why `Process` and not `exec()`:* the current code builds a shell string with `escapeshellarg` and cannot enforce a timeout. `Process` takes an argument array — no shell — and supports `setTimeout()`, which the spec's *"Rendering does not hang indefinitely"* scenario requires.

*Alternative considered:* a long-running HTTP renderer. Rejected as premature for three documents generated by hand.

### Supply fonts from `config('resume.fonts')`

`@font-face` rules point at the static TTFs in `resources/resume/assets/fonts`, by absolute path.

*Why:* the template's own `word/fonts/*.odttf` parts would have to be deobfuscated at render time, and `FontEmbedder` writes those parts *from* these same TTFs — so the files on disk are already the faces the DOCX embeds. Using them directly makes drift between the two formats impossible without an extra code path. This is also what removes the class of bug `CLAUDE.md` documents at length: no machine-installed variable font can reach the PDF, because nothing reads an installed font at all.

### Move the signature's placement constants to configuration

`SIGNATURE_RISE`, `SIGNATURE_GAP` and `SIGNATURE_INDENT` are `protected const` on `CoverLetterBodyComposer`. Both formats now need them. Move them to `config/resume.php` in EMU/twips as they are today, and have both composers read them.

*Why:* these were tuned by hand against a rendered page. Two copies would drift on the next adjustment, and the spec requires the PDF to reproduce the DOCX's placement. `SignatureImageService` needs no change at all: it already returns keyed PNG bytes plus `cx`/`cy`, which the HTML path writes to a temp file and sizes from.

### Write the PDF atomically

Render to a temporary path, then move into the output directory on success; on failure or timeout, delete the temporary file and leave whatever was there untouched.

*Why:* the spec forbids leaving a partial PDF behind, and requires that a failed regeneration not present the previous PDF as current. The move satisfies the first; reporting the failure to the caller — which all three services already do — satisfies the second.

### Drop the DOCX precondition

`generatePdf()` no longer checks `docxExists()`. All three services render from their own source: the main resume from `getDocxData()`, a targeted resume from its stored `tailored_data`, a cover letter from its stored fields.

*Why:* once nothing converts the DOCX, the check tests an unrelated file. Callers branching on the `DOCX file not found. Generate DOCX first.` error drop that branch; the spec delta records this as the migration.

### Production install

RHEL-family host, installed with `dnf`:

```bash
sudo dnf install -y weasyprint          # or python3-weasyprint, depending on repo
```

If neither package is available in the host's enabled repositories, install into a dedicated virtualenv instead and point `config('resume.weasyprint')` at its binary:

```bash
sudo dnf install -y python3 python3-pip pango
sudo python3 -m venv /opt/weasyprint
sudo /opt/weasyprint/bin/pip install weasyprint
```

`pango` is the only non-Python native dependency — confirmed against the Alpine package's own dependency list (`pango`, plus Python packages: `pydyf`, `fonttools`, `pillow`, `cssselect2`, `tinycss2`, `tinyhtml5`, `pyphen`, `brotli`, `zopfli`, `cffi`). LibreOffice already depends on more of that stack than WeasyPrint does, so removing it and adding this is a net reduction either way.

The binary is executed by `apache`, so under SELinux it must carry an executable context — `bin_t` for a venv install outside the package manager's control:

```bash
sudo semanage fcontext -a -t bin_t "/opt/weasyprint/bin(/.*)?"
sudo restorecon -Rv /opt/weasyprint
```

A distro package lands already labeled and needs neither command.

The local container gets `apk add --no-cache weasyprint` in the Dockerfile's `development` stage, replacing `libreoffice-writer`. The `base` and `production` stages stay untouched, as they are today.

## Risks / Trade-offs

- **PDF line breaks will not match the DOCX's** → Accepted, and recorded in the spec. Tests assert section-level parity (each section begins on the same page, same page count) rather than glyph positions, which is the property that actually matters and the one the spike verified.

- **A template style the translator does not recognize renders unstyled** → It inherits through `basedOn` and logs, rather than failing. The task list includes an assertion that every style id the template's `styles.xml` defines is either translated or explicitly known-ignorable, so a new style added in Word is caught by the test suite rather than in a PDF.

- **WeasyPrint missing on the host after deploy** → The same failure mode LibreOffice has today, which is how this work started. Mitigated by a `resume:check-pdf-renderer` smoke command run as a deploy step, and by the spec's requirement that an unavailable renderer names itself as the cause.

- **SELinux blocks execution from a venv path** → The `semanage`/`restorecon` steps above; verified on the host before LibreOffice is removed.

- **Removing LibreOffice is hard to undo quickly** → Removal is the last step of the migration, not the first. It happens only after PDFs have been generated and eyeballed on the live host.

- **Python version drift on the host** → WeasyPrint 63 supports Python 3.9+. A venv pins its own dependency set independently of whatever else on the host uses the system Python.

- **Overlap with `offload-resume-docx-generation`** → Both touch the same three `generatePdf()` call sites, but that change moves the call and this one changes its body. Whichever lands second rebases; neither blocks the other.

## Migration Plan

1. Land the code with `config('resume.weasyprint')` defaulting to `weasyprint` on `PATH`. LibreOffice stays installed on the host and nothing references it.
2. Deploy. Run `php artisan resume:check-pdf-renderer` on the host; fix packaging or SELinux labelling until it passes.
3. Regenerate the main resume, one targeted resume and one cover letter on the live host. Compare each against the DOCX it was generated alongside: section placement, fonts (`pdffonts` shows no substituted family and no `-Thin` face), the two-column skills region, and the cover letter's signature.
4. Only then remove LibreOffice from the host (`sudo dnf remove libreoffice-core`, which takes the 339 MB with it) and drop `libreoffice-writer` from the Dockerfile.

**Rollback:** through step 3, reverting the deploy restores the previous behaviour with LibreOffice still present. After step 4, rollback additionally requires reinstalling LibreOffice — which is why step 4 is separated from the deploy and gated on a human having looked at the output.
