## 1. Confirm the starting state

- [x] 1.1 Check stored targeted resumes for a literal `---` inside a skills section (`php artisan tinker --execute` over `tailored_data`), and verify none would change meaning under the new marker rule — record the count checked in the task notes (risk noted in design.md) — **checked 2026-09-20: 29 rule-like lines across targeted resumes 25, 32, 39; none inside a `# Skills` H1 section. All three are legacy free-form resumes (id 25 uses `# Jason Vertucio` + `## Skills`; id 39 has no headings), so no column behavior changes — only their literal `---` paragraphs stop printing.**
- [x] 1.2 Add a failing test to `MarkdownToOpenXmlConverterTest` that walks the emitted paragraphs and asserts the skill category paragraphs fall inside the `w:num="2"` section; verify it fails against the current implementation, confirming the column bug described in design.md — Context

## 2. Section labels

- [x] 2.1 Add `SECTION_ALIASES` (lowercased heading → canonical key, covering both bare keys and display labels) and `SECTION_LABELS` (canonical key → printed label) to `MarkdownToOpenXmlConverter`, and resolve H1 text through `SECTION_ALIASES` in `parseLines()`; verify with a unit test that `# Professional Experience` and `# Experience` both produce `JobTitle`/`CompanyInfo` styling for their H2/H3
- [x] 2.2 Emit the display label in `buildLineXml()` for H1 lines; verify with unit tests that `# Skills` renders `Technical Skills`, `# Experience` renders `Professional Experience`, `# Projects` renders `Selected Projects`, `# Education` renders `Education`, and that no bare `Skills`/`Experience`/`Projects` heading text remains
- [x] 2.3 Verify an unrecognized H1 (e.g. `# Certifications`) still renders its own text as `Heading1` with no styling change, via a unit test
- [x] 2.4 Confirm `# Summary` is still suppressed and its body renders unheaded, via the existing suppression test

## 3. Column regions

- [x] 3.1 Add a `rule` line type to `parseLines()` matching `/^(?:-{3,}|\*{3,}|_{3,})$/`, checked before the bullet rule; verify with a unit test that a `---` line emits no paragraph, inside or outside a skills section
- [x] 3.2 Add the pre-pass that determines, for each `Skills` H1 section, whether that section contains a `rule` marker (scanning to the next H1 or end of input); verify indirectly through the column tests in 3.4–3.6
- [x] 3.3 Replace `pendingSkillsColumnsStart` and the emit-before-content pattern with the `requiredColumns` / `currentColumns` state machine from design.md — D3, emitting a break only on a column-count change and carrying the count of the region being closed, plus the end-of-`convert()` close for a trailing two-column region
- [x] 3.4 Verify the top+other shape: markdown with a `---` marker emits `heading, top content, break(1), other content, break(2)`, with the top paragraphs in the single-column region and the other paragraphs inside a non-empty `num="2"` region (makes 1.2 pass)
- [x] 3.5 Verify the no-marker shape (targeted resume): the whole skills section falls inside a non-empty `num="2"` region
- [x] 3.6 Verify the top-only shape: no column break is emitted at all and the document contains no `w:num="2"` region
- [x] 3.7 Verify sections after the skills section return to full width: an `# Experience` section following a two-column skills section sits outside the `num="2"` region
- [x] 3.8 Verify a document ending in the skills section still closes its two-column region at end of `convert()`
- [x] 3.9 Verify `test_non_skills_sections_do_not_get_column_breaks` still passes — a document with no skills section emits no `w:cols` at all

## 4. Composer marker

- [x] 4.1 Emit `---` between the `top` and `other` groups in `ResumeMarkdownComposer::skillsSection()`, only when there is at least one top category; verify with unit tests covering top+other, other-only (no marker), top-only (marker present, nothing after it), and empty skills (section omitted entirely)
- [x] 4.2 Run the composer and converter unit tests and verify both pass — **note: `php artisan test` is not registered in this project; used `vendor/bin/phpunit` per CLAUDE.md. 44/44 pass.**

## 5. Site resume page

- [x] 5.1 Change the heading in `resources/views/components/resume/experience.blade.php` from "Experience" to "Professional Experience"; verify the four headings on `/resume` read Technical Skills, Professional Experience, Selected Projects, Education
- [x] 5.2 Check whether any test or snapshot asserts the literal heading "Experience" (`grep -rn '>Experience<' tests/ resources/`) and update it if so; verify by running any test it names

## 6. End-to-end verification

- [x] 6.1 Run `vendor/bin/phpunit --filter=Resume` and verify the resume suite passes — **283 tests, 1107 assertions, 0 failures (4 pre-existing PHPUnit notices)**
- [x] 6.2 Generate the main resume DOCX from `/admin/resume/preview`, unzip `word/document.xml`, and verify the break sequence matches design.md — D3 for the real data's shape (top categories present → `heading, top, break(1), other, break(2)`)
- [x] 6.3 Open the generated PDF and visually confirm: headings read in caps as TECHNICAL SKILLS / PROFESSIONAL EXPERIENCE / SELECTED PROJECTS / EDUCATION, top skills run full width, remaining categories flow in two columns, and experience onward is full width
- [x] 6.4 Compare the generated PDF against the old template's layout and confirm the vertical spacing around the skills section is not worse than before (risk noted in design.md — the `break(1)` after the Skills heading is no longer emitted in the top+other case)
- [x] 6.5 Render a targeted resume DOCX from existing stored `tailored_data` (id 53, rendered to a scratch path rather than via `TargetedResumeDocumentService`, which hardcodes its output dir and writes `docx_path` back to the row) and verify its headings carry the display labels and its skills section is two-column throughout
- [x] 6.6 Generate a cover letter and verify no section headings and no column breaks — **`CoverLetterBodyComposer` does route `message_body` through the converter, so letters are not structurally immune; verified all 9 stored letters carry zero H1s and zero rule lines, and a rendered letter emits 0 `w:cols`, 0 `Heading1`, 0 `sectPr`. proposal.md corrected.**

## 7. Documentation

- [x] 7.1 Update CLAUDE.md — *Document Generation* to describe the key/label split, the `---` marker, and the corrected break placement, replacing the current "`# Skills` switches into a two-column flow" wording; verify the described contract matches the shipped constants
