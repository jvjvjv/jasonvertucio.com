## Context

See `proposal.md` — *Why*. Three facts about the current code shape the approach:

1. **The H1 text is load-bearing.** `MarkdownToOpenXmlConverter` stores the H1 text in `$currentSection` and keys `CONTEXTUAL_STYLES` (`Experience` → `JobTitle`/`CompanyInfo`, `Education` → `CompanyInfo`) and the column logic (`$line['text'] === 'Skills'`) off it. `TargetedResumeService`'s prompt instructs the model to emit `# Skills` / `# Experience` / `# Projects` / `# Education`, and every stored `tailored_data` Markdown already carries those. Renaming the *keys* would silently strip styling from every existing targeted resume.

2. **Heading case comes from the style, not the text.** `Heading1` and `Heading2` in `2026 template.docx` both set `<w:caps/>`. The old template's document.xml stored the literal text `TECHNICAL SKILLS`, but only because it was typed that way in Word — the caps rendering does not depend on it. So the labels are stored in title case and the style uppercases them, which also keeps the stored text matching the site's `Technical Skills`.

3. **A `sectPr` describes the section that *ends* with its paragraph.** This is the root of the column bug. `processLine()` emits `buildColumnBreak(2)` *before* the first skills paragraph, so the two-column section contains only that empty break paragraph, and the skills content lands in the next section — closed by the `buildColumnBreak(1)` emitted at the following `# Experience`. Verified against `storage/app/resumes/2026.1.0 Jason Vertucio.docx`:

   ```
   4  Heading1   'Skills'
   5  -          ''    <<< SECTBREAK w:num="1"     ← closes [letterhead … heading] at 1 col  ✓
   6  -          ''    <<< SECTBREAK w:num="2"     ← closes an EMPTY section at 2 col        ✗
   7  Heading2   'Languages'
   8  Normal     'PHP, JavaScript'
   9  -          ''    <<< SECTBREAK w:num="1"     ← closes [skills content] at 1 col        ✗
   ```

   The existing tests pass because they assert only that the substrings `w:num="1"` and `w:num="2"` appear somewhere in the fragment.

The old (deleted) `2026 resume template.docx` is the reference for intended layout. Recovered from `git show '124da7b^:resources/resume/2026 resume template.docx'`, its break sequence is: `num=1` after the summary, heading + `{#top}` loop, `num=1` after `{/top}`, `{#other}` loop, `num=2` after `{/other}`.

## Goals / Non-Goals

**Goals:**

- Separate the section *identifier* (styling contract) from the section *label* (printed text), so labels can change without touching stored content or the agent prompt.
- Make the emitted column breaks actually enclose the content they govern.
- Express the top/other skills split in the Markdown layer, so it survives the round trip through `MarkdownToOpenXmlConverter` without the converter needing access to the structured resume data.
- Leave targeted resumes — which have no top/other distinction — rendering exactly as they do once the column bug is fixed.

**Non-Goals:**

- Restoring the deleted per-type templates, or moving any layout decision back into a `.docx` file.
- Generalizing to arbitrary N-column regions or a user-configurable label set. Four labels, two column counts.
- Changing `CoverLetterBodyComposer`, which emits OOXML directly and has no section headings or column regions.

## Decisions

### D1 — Labels map from a canonical section key, resolved at parse time

`MarkdownToOpenXmlConverter` gains two constants:

- `SECTION_ALIASES`: lowercased heading text → canonical key. Covers both the bare key and its display label (`skills`, `technical skills` → `Skills`; `experience`, `professional experience` → `Experience`; `projects`, `selected projects` → `Projects`; `education` → `Education`).
- `SECTION_LABELS`: canonical key → printed label (`Skills` → `Technical Skills`, `Experience` → `Professional Experience`, `Projects` → `Selected Projects`, `Education` → `Education`).

`parseLines()` resolves an H1 through `SECTION_ALIASES` and stores the canonical key as the line's `text`, so `$currentSection`, `CONTEXTUAL_STYLES` and the column logic keep working against the keys they already use — unchanged. `buildLineXml()` looks up `SECTION_LABELS` when emitting an H1. An unrecognized heading passes through unmapped as both key and label, preserving today's behavior.

Resolving the alias at parse time rather than at render time is what buys requirement *"Body content using display labels still renders styled"*: a hand-edited targeted resume headed `# Professional Experience` canonicalizes to `Experience` and gets `JobTitle`/`CompanyInfo` styling, instead of falling through to `Heading2`/`Heading3`.

*Alternative considered:* rename the keys themselves and update `TargetedResumeService`'s prompt. Rejected — it would require rewriting every stored `tailored_data` Markdown, a data migration for a cosmetic change, and it leaves the system with no tolerance for a heading the model phrases slightly differently.

*Alternative considered:* carry the label in `ResumeMarkdownComposer` (emit `# Technical Skills` directly). Rejected — it puts the label on only one of the two resume paths, forces the converter to key its styling off the label anyway, and diverges the two resume types rather than unifying them.

### D2 — A `---` thematic break marks where the top skills end

`ResumeMarkdownComposer::skillsSection()` emits the `top` categories, then a `---` line, then the `other` categories. The marker is emitted only when there is at least one top category.

`parseLines()` gains a `rule` line type matching `/^(?:-{3,}|\*{3,}|_{3,})$/` (checked before the bullet rule, which requires whitespace after the marker character and so does not match `---`). A `rule` line emits no paragraph; it only advances the column state. Outside the skills section it is discarded — today it would render as a literal `---` paragraph, which was never intended.

*Alternative considered:* a second H1 for the other-skills group. Rejected — the old template printed one heading, and an H1 is exactly what terminates a section in this converter.

*Alternative considered:* have the composer emit a marker unconditionally and treat its absence as "legacy". Rejected in favor of the lookahead in D3, which needs no sentinel for the top-only case.

### D3 — Replace the `pending` flag with a column state machine

`pendingSkillsColumnsStart` and the "emit the break before the content" pattern are removed. The converter instead computes, per parsed line, the number of columns that line's content requires, and emits a break only when that number *changes* — carrying the count of the region being **closed**:

```
requiredColumns(line):
    1  if the line is not inside a Skills section
    1  if inside a Skills section and it is the H1 heading
    1  if inside a Skills section that contains a rule marker, before that marker
    2  if inside a Skills section that contains a rule marker, after that marker
    2  if inside a Skills section that contains no rule marker   (targeted resumes)

currentColumns = 1                       # the template's body sectPr is 1 column
for each line:
    n = requiredColumns(line)
    if n != currentColumns:
        emit buildColumnBreak(currentColumns)   # closes the preceding region at ITS count
        currentColumns = n
    emit buildLineXml(line)

at end of convert():
    if currentColumns != 1:
        emit buildColumnBreak(currentColumns)   # close a trailing two-column region
```

Whether a skills section contains a rule marker is resolved in a pre-pass over the parsed lines, scanning from each `Skills` H1 to the next H1 or end of input — the lines are already fully parsed into an array before emission begins, so no lookahead machinery is needed.

This is what satisfies the spec's *"Column regions govern the content they enclose"*. Traced against the three shapes:

| Input | Emitted |
| --- | --- |
| top + other | heading, top content, `break(1)`, other content, `break(2)`, next section |
| other only (targeted resume) | heading, `break(1)`, other content, `break(2)`, next section |
| top only | heading, top content, next section — **no breaks at all** |

The top-only case falls out for free: the count never leaves 1, so no break is emitted and no empty two-column region is left behind. Content after the final `break(2)` is governed by the template's own body `sectPr`, which is `<w:cols w:space="720"/>` — one column — so the experience, projects and education sections return to full width without an explicit break.

The `break(1)` that currently follows every `# Skills` heading disappears in the top+other case, because the heading and the top categories are then in the same single-column region. That matches the old template, which had no break between `TECHNICAL SKILLS` and the `{#top}` loop.

### D4 — Test the emitted structure, not the emitted substrings

The current column tests assert `assertStringContainsString('<w:cols w:num="2"', $xml)`, which is true of a broken document. New tests parse the emitted fragment, walk its paragraphs, and assert which paragraphs fall inside which column region — i.e. that the skill category paragraphs are in the `num="2"` section and that section is non-empty.

This is the same lesson CLAUDE.md already records under *Placeholders split across runs*: a substring check can pass while the behavior it stands for is silently broken; assert on the rendered value.

## Risks / Trade-offs

- **A stored targeted resume already containing a literal `---`** would now be read as a column marker instead of rendering as a stray `---` paragraph. → Only meaningful inside a `# Skills` section; elsewhere it is discarded, which is strictly better than printing it. Worth a quick check of stored `tailored_data` before implementing, listed as a task.

- **The alias table accepts a heading in two spellings**, so `# Skills` and `# Technical Skills` produce identical output and a reader of the Markdown cannot tell which is canonical. → Accepted deliberately: tolerance for both is the point (D1). `ResumeMarkdownComposer` and the agent prompt both emit the bare key, so the canonical form is the one the system actually produces.

- **Removing the `break(1)` after the `# Skills` heading changes vertical spacing** in the top+other case, since each break paragraph occupies a line. → This restores the old template's spacing rather than inventing new spacing, but it is a visual change that should be confirmed against a rendered PDF, not just asserted in a unit test.

- **Column flow is balanced by Word/LibreOffice**, not by the generator, so a very short or very long "other skills" group may break across the two columns unevenly. → Same behavior as the old template; out of scope.

## Migration Plan

No data migration. Deploy is code-only:

1. Merge and deploy as usual (`npm run build` is unaffected — no frontend asset change beyond one Blade string).
2. Regenerate the current resume version's DOCX/PDF from `/admin/resume/preview` so the downloadable file matches the site.
3. Previously generated targeted resumes and cover letters on disk keep their old rendering until regenerated; nothing rewrites them in place.

Rollback is a revert — no schema, config, or stored-content change to undo.

## Open Questions

None.
