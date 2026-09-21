## Context

See `proposal.md — Why` for motivation. The constraints that shape the approach:

**Three rendering strategies exist today.**

| Document | Template | Renderer | Body source |
|---|---|---|---|
| Main resume | `2026 resume template.docx` (header + `{#skills}`/`{#experience}`/`{#projects}`/`{#education}` loops) | `scripts/generate-resume.js` — `docxtemplater` + `pizzip` + expressions parser, via `shell_exec` | `ResumeDataServiceContract::getDocxData()` — a flattened array |
| Targeted resume | `2026 targeted resume template.docx` (header only) | `TargetedResumeDocumentService` — PHP `ZipArchive` + `DOMDocument` + `MarkdownToOpenXmlConverter` | `tailored_data.content` Markdown |
| Cover letter | `2026 cover letter template.docx` (header part + logo + `{date}`/`{greeting}`/`{@messageBody}`/`{closing}`/`{signature}`) | `scripts/generate-cover-letter.js` — `docxtemplater` rawxml + `scripts/markdownToOoxml.js`, via `shell_exec`, with PHP pre-normalizing split placeholder runs | `CoverLetter` columns; `message_body` is Markdown |

**`resources/resume/2026 template.docx` is already the right base.** Inspection confirms it is the targeted-resume template with the header part removed: identical custom styles (`JobTitle`, `CompanyInfo`, `KeyTechnologies`, `Heading1`–`Heading3`, `ListParagraph`), a `word/numbering.xml` that defines `numId` 1–6 (the converter hardcodes `BULLET_NUM_ID = '6'`), one continuous `sectPr` with the resume's page margins, and only the five header placeholders. `Heading1` is centered with a `#B35E06` bottom rule; `JobTitle` is `#1B587C` — the brand blue already lives in the template. No template surgery is required to adopt it.

**`MarkdownToOpenXmlConverter` is section-aware by heading text.** `CONTEXTUAL_STYLES` keys on the current `h1` text: under `Experience`, an `h2` becomes `JobTitle` and an `h3` becomes `CompanyInfo`; under `Education`, `h3` becomes `CompanyInfo`. An `h1` of exactly `Skills` switches the following content into a two-column flow. A bullet starting `Key Technologies:` gets the `KeyTechnologies` style. This means the Markdown a document feeds the converter is effectively a contract, and it is already written down: `TargetedResumeService`'s system prompt (around line 847) specifies `# Summary` / `# Skills` / `# Experience` / `# Projects` / `# Education`, with `## Job Title`, `### Company - Location - Start - End`, and `-` bullets.

**Signature source.** `resources/resume/signature.png` is 199×415 px, 8-bit RGBA, strokes in a bright blue (≈`#1F35DE`) on transparency.

## Goals / Non-Goals

**Goals:**

- One template file, one rendering strategy, for all three document types.
- Keep the main resume's generated content equivalent to what it produces today — same sections, same order, same data — even though the mechanism changes.
- Make the signature a self-contained, reusable piece: source-dimension-driven sizing, brand recoloring, and graceful absence.
- Leave every caller's public surface (`generateDocx()` / `generatePdf()` return shapes, controller responses, MCP tool responses) unchanged, so nothing outside the generation layer has to move.

**Non-Goals:**

- Redesigning the documents' visual appearance. Whatever `2026 template.docx` renders is the design; this change does not adjust typography, spacing, or color beyond the signature.
- Changing the targeted-resume Markdown contract or the AI system prompt that produces it.
- Moving generation off the request path. That is the separate `offload-resume-docx-generation` change; see *Interaction with in-flight changes* below.
- Reworking `MarkdownToOpenXmlConverter`'s parser. It gains image support; its Markdown handling stays as-is.

## Decisions

### D1: Standardize on the PHP `ZipArchive` + `MarkdownToOpenXmlConverter` path; drop Node entirely

**Chosen:** Extract `TargetedResumeDocumentService`'s generic machinery — `replaceSimplePlaceholders()`, `replaceSplitPlaceholderRuns()`, and `appendResumeContent()` (whose docblock already admits it is mis-named) — into a `DocumentRenderer` in `app/Services/Resume/`. It takes a template path, a placeholder map, an OOXML body fragment, and optional embedded media, and writes a DOCX. All three services call it.

**Why:** Of the two existing strategies, only the PHP one works against a body-less template, which is what the shared template is. Keeping `docxtemplater` would mean putting resume loops back into the shared template, which contradicts the whole point. Dropping `shell_exec` also removes three process spawns per document, the JSON-over-stdout error channel, and the `node` runtime dependency from document generation — worth noting because `offload-resume-docx-generation` is currently justified partly by that subprocess cost.

**Alternative considered:** Keep `docxtemplater` and give the shared template a single `{@body}` rawxml tag, with each service injecting its own OOXML. This preserves the Node path and is a smaller diff, but it keeps two renderers alive (the targeted resume would still need the PHP path for its split-run handling, or would have to be rewritten onto rawxml), keeps the `shell_exec` round-trip, and makes the template's contract "one invisible tag you must not delete in Word" — fragile against exactly the Word round-trips that already split `{url}` across runs.

### D2: Compose the main resume's body as Markdown, then reuse the converter

**Chosen:** A `ResumeMarkdownComposer` turns `getDocxData()` into the same Markdown dialect the targeted resume uses, and the existing converter turns that into OOXML.

**Why:** The converter's contextual styling is driven by heading text, so producing the documented `# Skills` / `# Experience` / `# Education` structure is what makes the main resume render with `JobTitle`/`CompanyInfo`/two-column skills at all. Going structured-array → OOXML directly would mean a second, parallel body builder that has to re-derive the same style decisions — the exact duplication this change exists to remove. Markdown as the intermediate also makes the main resume diffable and testable as text.

**Mapping from `getDocxData()`:**

| Markdown | Source |
|---|---|
| `# Summary` + paragraph | `summary` |
| `# Skills`, then `## {title}` + `{listJoined}` | `skills.top[]` then `skills.other[]` |
| `# Experience`, `## {jobTitle}`, `### {company} - {location} - {dateStart} - {dateEnd}`, `- {bullet}` | `experience[]` |
| `# Projects`, `## {projectName}`, description paragraph, `- {bullet}` | `projects[]` |
| `# Education`, `## {degree}`, `### {institution} - {dateRange}`, description | `education[]` |

**Consequence to accept:** section headings change from the old template's `SUMMARY` / `TECHNICAL SKILLS` / `PROFESSIONAL EXPERIENCE` / `SELECTED PROJECTS` / `EDUCATION` to `Summary` / `Skills` / `Experience` / `Projects` / `Education`, and education's flat `{metaLine}` (location • dates • degree • level) becomes a degree heading over an institution line. This is the price of the two resumes rendering identically, which is the point. It is a visible change to the published resume and should be eyeballed in Word before the first real download.

### D3: Build the cover letter body as OOXML, not by templating placeholders

**Chosen:** A `CoverLetterBodyComposer` emits the letter's OOXML directly: a date paragraph, the company address block (one paragraph per line), the greeting, the converted `message_body` Markdown, the closing, the signature image, and the typed signature name. It is appended to the shared template exactly as the targeted resume's body is.

**Why:** This is what "the existing cover letter template is replicated" resolves to once the template itself is gone — the *structure* is reproduced in code. It also removes `normalizeSplitPlaceholders()`, which exists only because Word fragments `{companyAddress}` and friends across runs; code-generated paragraphs cannot fragment. The `Normal`, `ListParagraph`, and `Heading*` styles the converter emits all exist in the shared template, and `numbering.xml` is present, so `generate-cover-letter.js`'s "inject numbering.xml if missing" fallback is no longer needed either.

**Note on the header placeholders:** the shared template's `{name}`/`{title}`/`{email}`/`{phone}`/`{url}` block sits in the document body, not a header part. Cover letters will therefore open with the same identity block the resumes use, above the date — which is a conventional letterhead position, and is what "shared template's look wins" was chosen to mean.

### D4: Signature as a standalone service producing a ready-to-embed PNG + drawing XML

**Chosen:** `SignatureImageService` reads the PNG with GD (`imagecreatefrompng`), recolors it, and returns the bytes plus pixel dimensions. The renderer adds the bytes at `word/media/signature.png`, adds a `Relationship` of type `.../image` to `word/_rels/document.xml.rels` with an id that does not collide with existing ones, ensures `<Default Extension="png" .../>` exists in `[Content_Types].xml`, and emits an inline `w:drawing` sized in EMU.

**Recoloring:** iterate pixels; for each with alpha < fully-transparent, write R/G/B = `0x1B`/`0x58`/`0x7C` and keep the source alpha byte. Preserving alpha is what keeps anti-aliased edges smooth — thresholding alpha to on/off would produce the jagged outline the spec forbids. `imagealphablending(false)` + `imagesavealpha(true)` are required or GD will composite the alpha away on write.

**Sizing:** 2 inches tall → `cy = 2 × 914400 = 1828800` EMU. `cx = round(1828800 × width ÷ height)`, which for 199×415 is ≈`877,000` EMU (≈0.96″). Computing from the actual image dimensions, not constants, is what satisfies the "replace the signature file" scenario.

**Why GD over ImageMagick:** GD ships enabled in the PHP images this app runs; adding an Imagick dependency for one recolor is not worth it. **Why not pre-generate a brand-blue PNG once and commit it:** it would work, but it silently decouples the committed asset from the brand color — a future `--color-primary` change would leave a stale signature with no code path pointing at the discrepancy.

**Why failure is non-fatal:** a cover letter without a signature is still a usable cover letter; a cover letter that failed to generate is not. The log line is the escape hatch. Everywhere else — a missing or unparseable *template* — failure is fatal, because there is no usable document without it.

### D5: `config('resume.template')` is the single source of truth

`TargetedResumeDocumentService` and `CoverLetterDocumentService` drop their `base_path(...)` constructor assignments and read the config value, as `GeneratesResumeDocuments::initDocumentPaths()` already does. `config/resume.php`'s `template` key is repointed to `resource_path('resume/2026 template.docx')` and its docblock updated to say it backs all three document types.

Per the user's decision, the file keeps its current name — no rename to `2026 resume.docx`.

### D6: Dependency removal is deferred, deletion of dead scripts is not

The three Node scripts lose their callers and are deleted in this change. Removing `docxtemplater`, `pizzip`, and `docxtemplater/expressions.js` from `package.json` is *not* done here: `npm ci` on the production host is part of the deploy, and pruning dependencies is an independently reversible step that adds nothing to this change's verification. Confirm no other importer first (`grep -rn "docxtemplater\|pizzip" resources/js scripts`), then drop them in a follow-up.

## Risks / Trade-offs

- **The main resume's rendered output changes visibly** (D2: heading casing, education layout, skills column flow) → Generate the current version's DOCX before and after and compare in Word. This is the one part of the change a test suite cannot fully certify; budget a manual look. If the heading casing matters, it is fixable in the template's `Heading1` style (`<w:caps/>`) rather than by special-casing the composer.
- **Cover letters lose the logo image from their old header part** → Accepted and chosen deliberately ("shared template's look wins"). If it is missed, the fix is to add the image to `2026 template.docx` in Word, where all three documents then inherit it — which is the outcome this change is built to enable.
- **`MarkdownToOpenXmlConverter` has never rendered the main resume's data** → Real content may expose parser gaps (a project description with a stray `#`, a skill list containing `*`, an em-dash in a company name). Mitigate with feature tests that run the real current resume version's `getDocxData()` through the composer and converter and assert the output is well-formed XML with the expected style IDs.
- **Relationship-id collision when embedding the signature** → Do not hardcode an id (the retired `generate-cover-letter.js` hardcoded `rId99`). Scan existing `Id="rId(\d+)"` values and take max+1, as `generate-resume.js`'s hyperlink post-processing already does correctly.
- **`DOMDocument::saveXML()` round-tripping the whole `document.xml`** is how the targeted resume path already works, so its fidelity is proven for this template — but it is the step most likely to silently drop an exotic namespace. Assert on a real generated file (open the zip, parse `word/document.xml`) in tests rather than on the fragment alone.
- **GD may be absent or built without PNG support** in some environment → `SignatureImageService` degrades to no signature and logs, per D4. A deploy-time check is not warranted for a non-fatal path.

## Migration Plan

1. Land the renderer, composers, and `SignatureImageService` with tests, while the three services still point at their old templates — the new code is unreachable until step 2.
2. Repoint `config/resume.php` and the two services at the shared template; switch the three `generateDocx()` implementations to the renderer.
3. Regenerate the current resume version's DOCX and PDF, a sample targeted resume, and a sample cover letter. Open all three in Word. This is the gate.
4. Delete the three retired templates, the three Node scripts, and the stale `~$*.docx` lock files; add `~$*.docx` to `.gitignore`.
5. Update `CLAUDE.md`'s "DOCX Generation Flow" section, which currently describes `shell_exec()` into a Node script.

**Rollback:** steps 1–2 are a code revert; the retired templates are only deleted at step 4, so a revert before step 4 is complete on its own. After step 4, rollback also requires restoring the three `.docx` files from git — they are committed binaries, so `git checkout <sha> -- resources/resume/` recovers them. Generated documents already on disk in `storage/app/` are untouched by any of this; only newly generated ones differ.

**Deploy note:** nothing here is queued work, so the `supervisorctl restart` requirement in `CLAUDE.md` does not apply to this change on its own.

## Interaction with in-flight changes

`openspec/changes/offload-resume-docx-generation/` moves resume DOCX/PDF generation off the request path into a queued `GenerateResumeDocuments` listener. The two changes touch the same call site from opposite directions: that one changes *when* `generateDocx()` is called, this one changes *what it does*. They do not conflict at the spec level — no requirement in either contradicts the other — but whichever lands second should re-read the other's `tasks.md` before starting, and the queued listener must be restarted after this change deploys if it is already live. Neither change is a prerequisite of the other.

## Addenda found during implementation

Three defects surfaced that the planning phase had no way to see. All are recorded here so they are not re-investigated later.

**The `{url}` placeholder was never being substituted.** The extracted split-run detector required a text run whose content was exactly `{`. The real template stores the placeholder as `" \u2022 {"` / `url` / `}` — the opening brace is the *tail* of a run that also carries the preceding bullet separator. So no substitution ever fired and targeted resumes rendered the literal word `url` in the letterhead. The existing test passed because it asserted the contiguous string `{url}` was absent, which it always was. `DocumentRenderer` now matches a run *ending* with `{` and a closing run *containing* `}`, preserving the surrounding text on both sides.

**Cover letters lost their paragraph spacing.** The retired letter template set `w:after="160"` in its `pPrDefault`; the shared template's `pPrDefault` is empty because it is tuned for a dense resume. Moving letters onto it collapsed the body into one block. `CoverLetterBodyComposer` reapplies that same 160 to message-body paragraphs, skipping list items and column breaks, rather than changing the converter for the resumes too.

**The templates embedded only Thin font weights.** Every Montserrat and Josefin Sans face embedded in all four templates — including the three retired ones, so this long predates this change — was the Thin weight, including the files sitting in the Bold slots. Six styles request bold (`Title`, `Heading1`, `Heading2`, `JobTitle`, `Strong1`, `Heading2Char`), all of them Josefin Sans, and none had a bold outline to draw with. The generated PDF contained 27 `2 Tr` (fill-then-stroke) operations, which is how LibreOffice synthesizes fake bold. The cause was almost certainly the authoring machine having only the Thin variants installed, so Word resolved the family "Montserrat" to "Montserrat Thin" and embedded that. Fixed by re-embedding real statics instantiated from the upstream variable fonts under the existing obfuscation keys; synthetic-bold operations drop to 0 and page count is unchanged.

## PDF conversion is a stopgap

`generatePdf()` shells out to `libreoffice --headless --convert-to pdf`, which the local Docker image never installed — PDF conversion has never worked in the container, only on the host and in production. This change adds `libreoffice-writer` to the Dockerfile's **development** stage only: production does not run Docker (`CLAUDE.md` — the live host is apache + supervisord) and keeps using its own system LibreOffice, so the `base` and `production` stages are deliberately left alone.

That is an unblock, not an architecture. A separate exploratory change should follow this one to find a DOCX→PDF path with no LibreOffice dependency. Worth carrying into it: the DOCX templates embed their own fonts, and LibreOffice re-embeds them into the PDF — verified by the host having zero Montserrat/Josefin fonts installed while the output PDF still carried them. Any replacement must honor embedded fonts, or the documents lose their typography entirely.

## Open Questions

- ~~Whether to keep `Heading1` as title-case or restore all-caps via `<w:caps/>`.~~ **Resolved during implementation**: moot. The template's `Title`/`Heading1` styles already apply `<w:caps/>`, so `# Summary` renders as `SUMMARY` with no template edit. The rendered output matches the retired template's casing.
