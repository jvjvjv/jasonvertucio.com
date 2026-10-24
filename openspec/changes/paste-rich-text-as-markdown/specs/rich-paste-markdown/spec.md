## Purpose

Preserves the structure of formatted content (job postings, documents, web pages) pasted into textareas whose content is consumed as Markdown, by inserting a Markdown rendering of it instead of flattened plain text.

## ADDED Requirements

### Requirement: Rich paste into an opted-in field inserts Markdown

When the user pastes into an opted-in field and the clipboard carries an HTML flavor with meaningful formatting, the field SHALL insert a Markdown rendering of that content at the cursor position, replacing any selected text, instead of the clipboard's plain-text flavor. Text before and after the insertion point SHALL be preserved, and the cursor SHALL be placed at the end of the inserted text.

#### Scenario: Job posting pasted into an empty Job Description

- **WHEN** the user copies a job posting from a web page that has a heading, a bulleted list and bold labels, and pastes it into the empty Job Description field
- **THEN** the field contains Markdown with a `#`-prefixed heading, `-` list items and `**`-wrapped labels, rather than the flattened plain text

#### Scenario: Paste in the middle of existing text

- **WHEN** the field already holds text, the cursor sits between two paragraphs, and the user pastes formatted content
- **THEN** the Markdown is inserted at the cursor, the surrounding text is unchanged, and the cursor ends up after the inserted Markdown

#### Scenario: Paste over a selection

- **WHEN** the user selects part of the field's text and pastes formatted content
- **THEN** the selected text is replaced by the Markdown and the unselected text is unchanged

#### Scenario: Form state reflects the converted text

- **WHEN** formatted content has been pasted and converted
- **THEN** the value submitted with the form (or sent as the chat message) is the converted Markdown, exactly as shown in the field

### Requirement: Formatting that is converted

The Markdown rendering SHALL represent headings, paragraphs, line breaks, bold, italic, ordered lists, unordered lists (including nested lists), links, blockquotes, inline code, code blocks, horizontal rules and tables. Tables SHALL render as pipe tables with a header row. Links SHALL keep their visible text and target. Consecutive blank lines SHALL be collapsed so the result contains no more than one empty line in a row.

#### Scenario: Nested list keeps its nesting

- **WHEN** the pasted content contains a bulleted list with an indented sub-list
- **THEN** the sub-list items are indented beneath their parent item in the Markdown

#### Scenario: Link keeps its target

- **WHEN** the pasted content contains a hyperlink with the text "Apply here"
- **THEN** the Markdown contains `[Apply here](<the link's URL>)`

#### Scenario: Table becomes a pipe table

- **WHEN** the pasted content contains a table with a header row and two body rows
- **THEN** the Markdown contains a pipe table with that header row, a separator row and both body rows

### Requirement: Unsafe and non-text content is stripped

The conversion SHALL drop scripts, styles, comments, images, embedded media, form controls and markup with no text content. Only the text and structure listed under "Formatting that is converted" SHALL survive. No raw HTML tags SHALL appear in the inserted Markdown.

#### Scenario: Images are dropped

- **WHEN** the pasted content contains a company logo image between two paragraphs
- **THEN** the Markdown contains both paragraphs and no image syntax or image URL

#### Scenario: Styles and scripts are dropped

- **WHEN** the pasted HTML contains `<style>` and `<script>` elements alongside visible text
- **THEN** only the visible text and its structure appear in the Markdown; no CSS or script source appears

#### Scenario: No raw HTML leaks through

- **WHEN** the pasted HTML contains elements without a Markdown equivalent (e.g. `<span style=…>` or `<font>`)
- **THEN** their text content appears in the Markdown and the tags themselves do not

### Requirement: Unformatted or code content falls back to plain text

When the clipboard's HTML carries no meaningful formatting — only generic wrappers such as `div`, `span`, `p` and `br`, as produced by code editors and terminals — or when conversion fails or yields empty output, the field SHALL insert the clipboard's plain-text flavor unchanged.

#### Scenario: Code copied from a code editor

- **WHEN** the user copies several lines of indented code from a code editor whose clipboard HTML wraps each line in styled `div`/`span` elements, and pastes it
- **THEN** the field receives the plain text exactly as copied, with indentation intact and no Markdown escaping

#### Scenario: Conversion produces nothing

- **WHEN** the clipboard HTML converts to an empty string but the plain-text flavor is non-empty
- **THEN** the plain text is inserted

### Requirement: Plain-text paste is unchanged

A paste whose clipboard carries no HTML flavor — including the browser's "paste as plain text" (Ctrl/Cmd+Shift+V) — SHALL behave exactly as it did before this capability, with no conversion or escaping.

#### Scenario: Plain text from a text editor

- **WHEN** the user pastes text copied from a plain-text source that provides no HTML flavor
- **THEN** the field receives exactly that text

#### Scenario: Paste as plain text escape hatch

- **WHEN** the user copies a formatted web page and pastes with Ctrl/Cmd+Shift+V
- **THEN** the field receives the browser's plain-text rendering with no Markdown conversion

### Requirement: A converted paste is undoable

A converted paste SHALL be reversible with a single undo (Ctrl/Cmd+Z) in browsers that support programmatic text insertion into the undo history, returning the field to its exact pre-paste content and selection.

#### Scenario: Undo a converted paste

- **WHEN** the user pastes formatted content into a field holding "Hello", then presses Ctrl/Cmd+Z once
- **THEN** the field holds "Hello" again

### Requirement: Opted-in fields

Conversion SHALL apply to these fields, whose content is consumed as Markdown: the Job Description field on the New Session page, the Job Description field on an application's Discussion page, the chat message composer on every chat surface (application sessions, company intel, public chat bots), the cover letter Message Body, the system prompt Content field, and the targeted resume Markdown editor. Conversion SHALL NOT apply to any other textarea, in particular fields holding JSON, regular expressions, URL patterns or credentials.

#### Scenario: Chat composer converts

- **WHEN** the user pastes a formatted web page excerpt into the chat message composer
- **THEN** the composer holds the Markdown rendering, and sending the message sends that Markdown

#### Scenario: JSON field does not convert

- **WHEN** the user pastes formatted content into an AI system's JSON configuration field
- **THEN** the field receives the clipboard's plain text, as before

#### Scenario: Converted paste respects the composer's height rules

- **WHEN** a converted paste into the focused chat composer exceeds its maximum height
- **THEN** the composer stops growing at its cap and the content scrolls, exactly as for any other paste
