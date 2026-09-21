## 1. Share the Markdown parse between both formats

- [ ] 1.1 Create `app/Services/Resume/MarkdownDocumentParser.php` by extracting `parseLines()`, `stripCodeFences()`, `resolveColumns()`, `sectionHasRule()` and the section vocabulary (`SECTION_ALIASES`, `SECTION_LABELS`, `SUPPRESSED_HEADINGS`, `SKILLS_SECTION`, the `---` column marker) from `MarkdownToOpenXmlConverter`. Verify with a new `tests/Unit/Services/Resume/MarkdownDocumentParserTest.php` covering: bare identifiers and display labels both resolving to the same section key, `# Summary` suppression, the marker splitting the skills section, a marker outside the skills section being discarded, and code fences stripped.
- [ ] 1.2 Rewrite `MarkdownToOpenXmlConverter` to consume the parser's output instead of parsing itself, keeping its emit stage and public `convert()` signature unchanged. Verify `php artisan test --compact --filter=MarkdownToOpenXmlConverter` still passes with no test edits — the DOCX output must be byte-identical for the same input.

## 2. Translate the template's styles into CSS

- [ ] 2.1 Create `app/Services/Resume/StylesheetTranslator.php` that opens `config('resume.template')` and reads `docDefaults`, the named paragraph/character styles, and the body `sectPr`. Verify unit tests assert the unit conversions against the committed template's real values: `w:sz="26"` → `13pt`, `w:spacing w:before="240"` → `12pt`, `w:pgMar w:top="1037"` → `0.72in`, `w:pgSz` → `8.5in 11in`.
- [ ] 2.2 Resolve each style's `basedOn` chain so an inherited property is not dropped, and map `w:caps` → `text-transform:uppercase`, `w:pBdr/w:bottom` → `border-bottom`, `w:keepNext` → `break-after:avoid`, `w:color` → `color`, `w:i` → `font-style:italic`, `w:b`/`w:b w:val="0"` → `font-weight`. Verify a test asserts `CompanyInfo` emits italic with `font-weight` not bold (the template sets `<w:b w:val="0"/>`), and that `Heading1` inherits its font from its ancestor chain rather than being unstyled.
- [ ] 2.3 Cache the translated CSS keyed on the template's path and mtime. Verify a test translates, touches the template file, translates again, and asserts the second call re-read the file rather than serving the stale cache.
- [ ] 2.4 Log and inherit — never fail — on a style id the translator does not recognize. Verify a test asserts that every style id defined in the committed template's `styles.xml` is either translated or on an explicit known-ignorable list, so a style added in Word fails this test rather than silently rendering unstyled.

## 3. Compose the HTML document

- [ ] 3.1 Create `app/Services/Resume/MarkdownToHtmlConverter.php` emitting HTML from the shared parser's output, mapping each section key to the same styling contract the OOXML emitter uses (`# Experience` → job-title/company classes, `# Skills` → the column flow, `# Summary` suppressed). Verify unit tests assert headings print their display labels ("Technical Skills", "Professional Experience", "Selected Projects", "Education") and that no heading emits a bare identifier.
- [ ] 3.2 Emit the skills section's column structure: content before the marker full width, content after it in a two-column flow, and the whole section in two columns when no marker is present. Verify unit tests cover all three cases from the spec — both groups, no top groups, only top groups (no empty column region left behind).
- [ ] 3.3 Create `app/Services/Resume/HtmlDocumentComposer.php` that assembles the full document: translated CSS, `@font-face` rules built from `config('resume.fonts')`, the letterhead read from the template's own `document.xml` body paragraphs with placeholders substituted, then the converted body. Verify a test asserts the rendered letterhead contains the substituted values and no literal `{name}`/`{title}`/`{email}`/`{phone}`/`{url}`, including `{url}` which the template stores split across runs.
- [ ] 3.4 Reuse `DocumentRenderer`'s split-run placeholder reassembly rather than reimplementing it — extract it if needed. Verify the test from 3.3 passes against the committed template, where `{url}` is stored as `" • {"` / `url` / `}`.

## 4. Render the PDF

- [ ] 4.1 Add `weasyprint` (binary path, default `weasyprint`) and `weasyprint_timeout` to `config/resume.php`, documented on the same rule as `template`/`signature`/`fonts`. Verify `php artisan config:show resume` lists both.
- [ ] 4.2 Create `app/Services/Resume/PdfRenderer.php` that writes the composed HTML to a temp file, runs the binary via Symfony `Process` with an argument array (no shell) plus `-q` and the configured timeout, and moves the result into place only on success. Verify unit tests assert: a successful render returns the output path; a non-zero exit returns a failure result carrying the process output; a timeout returns a failure; and in every failure case no file is left at the output path.
- [ ] 4.3 Return a failure result naming a missing or non-executable binary as the cause, rather than a generic exec error. Verify a test points the config at a nonexistent path and asserts the error identifies the renderer as unavailable.

## 5. Move the signature's placement into configuration

- [ ] 5.1 Move `SIGNATURE_RISE`, `SIGNATURE_GAP` and `SIGNATURE_INDENT` from `CoverLetterBodyComposer` into `config/resume.php`, in the same units they use today, and read them in the composer. Verify `php artisan test --compact --filter=CoverLetter` passes with the DOCX output unchanged.
- [ ] 5.2 Place the signature in the HTML path from those same config values and the `cx`/`cy` that `SignatureImageService::build()` already returns, writing its keyed PNG bytes to a temp file. Verify a test asserts the emitted CSS positions the image with the configured rise and indent converted from EMU, and that it is positioned out of the text flow so it overlaps rather than displaces the sign-off.
- [ ] 5.3 Bind the closing, the signing space and the typed name so a page break cannot fall inside the sign-off. Verify a test renders a cover letter whose body fills the page and asserts the three parts land together.
- [ ] 5.4 Render the letter without a signature, and log, when `SignatureImageService::build()` returns null. Verify a test with an unreadable signature path asserts the PDF is still produced.

## 6. Wire the three document services

- [ ] 6.1 Replace the `exec('libreoffice ...')` body of `generatePdf()` in `app/Services/Concerns/GeneratesResumeDocuments.php` with a render from `getDocxData()`, and remove the `DOCX file not found. Generate DOCX first.` precondition. Verify a feature test generates the main resume PDF with no DOCX present and asserts success.
- [ ] 6.2 Same for `app/Services/TargetedResumeDocumentService.php`, rendering from the targeted resume's stored `tailored_data`. Verify a feature test asserts a targeted resume PDF renders with no DOCX present and that `pdf_path` is still recorded on the model.
- [ ] 6.3 Same for `app/Services/CoverLetterDocumentService.php`, rendering from the letter's stored fields. Verify a feature test asserts a cover letter PDF renders with no DOCX present.
- [ ] 6.4 Confirm the precondition is gone everywhere it was stated. The string `DOCX file not found. Generate DOCX first.` currently appears only in the three services themselves (`GeneratesResumeDocuments.php:153`, `CoverLetterDocumentService.php:105`, `TargetedResumeDocumentService.php:84`) — no caller branches on it — so 6.1–6.3 should remove all three occurrences. Verify `grep -rn "Generate DOCX first" app/` returns nothing, and update any test asserting that error. Use `DatabaseTransactions`, never `RefreshDatabase`, in any feature test touching the database.

## 7. Local container and deploy smoke check

- [ ] 7.1 Replace `apk add --no-cache libreoffice-writer` with `apk add --no-cache weasyprint` in the Dockerfile's `development` stage, updating the comment block above it to describe the rendering path. Leave `base` and `production` untouched. Verify `docker compose build app` then `docker exec jv-app weasyprint --version`.
- [ ] 7.2 Add a `resume:check-pdf-renderer` artisan command that renders a small fixture document and reports the binary's resolved path, its version, and whether the render succeeded. Verify it exits 0 in the container and non-zero with a clear message when `config('resume.weasyprint')` points at a nonexistent path.

## 8. Verify fidelity against the DOCX

- [ ] 8.1 Add a test asserting the PDF embeds every face the template's styles call for, with no substituted family and no face whose name ends in `-Thin`. Verify by inspecting the generated PDF's font entries, the same check `CLAUDE.md` prescribes with `pdffonts`.
- [ ] 8.2 Add a test asserting no synthesized bold: the PDF's content streams contain no text render mode `2 Tr`.
- [ ] 8.3 Add a test asserting section-level parity for the main resume — the same page count as the DOCX's converted output, and each section heading beginning on the same page. Do not assert on line breaks or glyph positions; the spec explicitly permits those to differ.
- [ ] 8.4 Add a test asserting a two-column skills region taller than one page continues onto the next page, redistributes across both columns there, splits no category, and loses no content.
- [ ] 8.5 Run the full suite with `php artisan test --compact` and confirm no regression beyond the pre-existing `AdminNavigationServiceTest::test_no_unflagged_non_admin_routes_in_navigation` failure, which is unrelated to this change and fails on a clean checkout.

## 9. Documentation

- [ ] 9.1 Rewrite `CLAUDE.md`'s **PDF conversion** section: PDFs are rendered, not converted; the template's `styles.xml` is translated to CSS at render time; fonts come from `config('resume.fonts')`; the DOCX is no longer a precondition. Remove the "this is a stopgap" paragraph, which this change answers. Verify by reading the section back against the delivered code.
- [ ] 9.2 Add the production install steps to `README.md` — the `dnf` package, the venv fallback, and the SELinux `semanage`/`restorecon` commands — near the existing RHEL/SELinux troubleshooting. Verify the commands match what step 10 actually ran on the host.
- [ ] 9.3 Update the **Document Generation** section's key-files list in `CLAUDE.md` with the new services, and note that `MarkdownDocumentParser` is now the one place the section-heading contract is implemented. Verify every file named in that list exists.

## 10. Production deployment — developer-owned

Every task in this group runs against the live host and is performed by the
developer, not by an agent. They are listed so the change is not considered
done until they are, and so the order and the gating in `design.md` —
*Migration Plan* is explicit. An agent implementing this change stops after
group 9 and hands these over.

- [ ] 10.1 Install WeasyPrint on the live host via `dnf`, falling back to a `/opt/weasyprint` venv if the distro package is unavailable. Verify `sudo -u apache /path/to/weasyprint --version` succeeds as the web-server user.
- [ ] 10.2 Apply the SELinux context if the venv fallback was used, and confirm apache can execute the binary. Verify `resume:check-pdf-renderer` exits 0 when run as `apache`.
- [ ] 10.3 Deploy the code with LibreOffice still installed, then regenerate the main resume, one targeted resume and one cover letter on the host. Verify each PDF by eye against the DOCX generated alongside it: section placement, fonts, the two-column skills region, and the signature crossing the sign-off.
- [ ] 10.4 Restart the queue worker per `CLAUDE.md` (`sudo supervisorctl restart <program-name>`), since `queue:work` holds its booted code in memory and will otherwise keep calling LibreOffice. Verify the worker picks up a newly queued job after the restart.
- [ ] 10.5 Only after 10.3 passes, remove LibreOffice from the host. Verify the reclaimed space with `df -h` and confirm PDF generation still succeeds afterwards.
