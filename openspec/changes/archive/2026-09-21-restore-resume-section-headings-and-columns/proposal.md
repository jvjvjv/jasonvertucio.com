## Why

Unifying the three document types onto one template (`2026-09-20-unify-document-templates`) moved the resume's section headings and skills layout out of the DOCX template and into `ResumeMarkdownComposer` / `MarkdownToOpenXmlConverter`, and two presentation decisions were lost in the move:

1. The prior template printed `TECHNICAL SKILLS`, `PROFESSIONAL EXPERIENCE`, `SELECTED PROJECTS`, `EDUCATION`. Generated documents now print the bare structural keys `Skills`, `Experience`, `Projects`, `Education`.
2. The prior template ran the **top** skill categories full width and flowed only the **remaining** categories in two columns. The composer now merges `top` and `other` into one section with no split.

Both losses are visible against the site's own resume page, which still renders "Technical Skills", "Selected Projects", full-width top skills, and a two-column grid for the rest — so the downloaded DOCX no longer matches what a viewer just read. The existing `document-template-rendering` spec already requires that "the section ordering and heading structure match the resume the site displays", so this is a regression against a stated requirement, not a new feature.

Investigating (2) surfaced a third defect, previously unnoticed: **the two-column flow does not work at all today.** `MarkdownToOpenXmlConverter::processLine()` emits the `w:cols w:num="2"` section break *before* the first skills paragraph rather than after the last one. In OOXML a `sectPr` describes the section that *ends* with its paragraph, so the two-column break terminates an empty section and the skills content falls into the following single-column section. Confirmed by unpacking a generated DOCX: the break sequence is `num=1` (heading), `num=2` (empty), skills content, `num=1`. The current unit tests assert only that the strings `w:num="1"` and `w:num="2"` appear somewhere in the fragment, so they pass while the layout is wrong.

## What Changes

- **Section headings gain display labels distinct from their structural keys.** `MarkdownToOpenXmlConverter` renders `# Skills` as `TECHNICAL SKILLS`, `# Experience` as `PROFESSIONAL EXPERIENCE`, and `# Projects` as `SELECTED PROJECTS`. `# Education` is unchanged and `# Summary` stays suppressed.
  - The H1 *text* remains the contract that drives styling (`Experience` → `JobTitle`/`CompanyInfo`, `Skills` → columns) and the targeted-resume agent prompt is unchanged, so stored `tailored_data` Markdown keeps rendering. Targeted resumes pick up the same labels for free.
  - The converter also canonicalizes an incoming heading that already uses a display label (`# Technical Skills` → the `Skills` section), so hand-edited targeted resumes do not silently lose their styling.
- **The site's resume page heading changes from "Experience" to "Professional Experience"**, so web and DOCX agree on all four labels.
- **`ResumeMarkdownComposer` marks where the top skills end** with a `---` thematic break between the `top` and `other` groups, emitted only when there are top categories.
- **The converter honors that marker**: within a `# Skills` section, content before the marker stays single-column and content after flows in two columns. A Skills section with no marker — every existing targeted resume — keeps the whole section in two columns.
- **The column breaks are emitted in the correct position** (after the content they govern, not before), so the two-column flow actually renders.
- **Tests assert rendered structure, not substring presence.** Column coverage is checked by walking the emitted paragraphs and confirming which paragraphs fall inside the `num="2"` section, following the same principle CLAUDE.md already records for placeholder substitution: assert on the rendered value, not on a string that can be present while the behavior is broken.

No data migration, no config change, and no change to the targeted-resume agent prompt or to any stored Markdown.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `document-template-rendering`: tightens "Main resume body is composed from structured resume data" — the requirement currently asserts the heading structure matches the site's resume, but does not state the labels or the skills column layout, which is how both drifted unnoticed. Adds requirements for the section heading labels, for the top-skills single-column / remaining-skills two-column split, and for column breaks that govern the content they enclose.

## Impact

Code:

- `app/Services/Resume/MarkdownToOpenXmlConverter.php` — display-label map, heading canonicalization, corrected column-break placement, `---` marker handling
- `app/Services/Resume/ResumeMarkdownComposer.php` — emit the `---` marker between top and other skill groups
- `resources/views/components/resume/experience.blade.php` — heading text
- `tests/Unit/Services/Resume/MarkdownToOpenXmlConverterTest.php` — structural column assertions, label assertions
- `tests/Unit/Services/Resume/ResumeMarkdownComposerTest.php` — marker assertions
- `CLAUDE.md` — the *Document Generation* section documents `# Skills` as "switches into a two-column flow"; it needs the label mapping and the marker

Behavior:

- Every regenerated resume, targeted resume, and cover letter DOCX/PDF. Already-generated files on disk are not rewritten; they are replaced on the next generation.
- Cover letters: `CoverLetterBodyComposer` emits the letter *structure* directly, but runs the `message_body` Markdown through the same converter, so a letter body is in principle exposed to both the label map and the `---` rule. Verified against the stored data: all 9 cover letters contain zero H1 headings and zero rule lines, so none change. A rendered letter emits no `w:cols`, no `Heading1` and no `sectPr`.

Out of scope:

- The resume JSON data files, the admin editor, and the AI-persona edit/approval flow
- The targeted-resume agent prompt in `TargetedResumeService`
- Any other template or layout change (spacing, fonts, page setup)
