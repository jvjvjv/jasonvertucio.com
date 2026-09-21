## Purpose

Establishes a single authoritative Word template behind every document the site generates — the main resume, targeted resumes, and cover letters — so the documents' shared visual identity is edited in one file, and defines how each document type composes its own body onto that common template.

## ADDED Requirements

### Requirement: A single configured template backs every generated document

The system SHALL render the main resume, targeted resumes, and cover letters from one configured DOCX template file. No document-generation path SHALL reference a template path of its own.

#### Scenario: All three document types resolve the same template

- **WHEN** the main resume, a targeted resume, and a cover letter are each generated
- **THEN** all three read the template from the same configured path
- **AND** changing that configuration value changes the template used by all three

#### Scenario: Template identity is visible in the output

- **WHEN** the shared template's header block is edited (name line, contact line, fonts, colors)
- **AND** each of the three document types is regenerated
- **THEN** every regenerated document reflects the edit, with no per-type divergence in that block

### Requirement: The shared template exposes a fixed header placeholder set

The shared template SHALL contain exactly the placeholders `{name}`, `{title}`, `{email}`, `{phone}`, and `{url}`, and SHALL NOT contain body-structure placeholders. Every document type SHALL substitute all five before emitting output, so no placeholder text reaches a finished document.

#### Scenario: Placeholders are substituted in every document type

- **WHEN** any document type is generated
- **THEN** the output contains no literal `{name}`, `{title}`, `{email}`, `{phone}`, or `{url}` text
- **AND** each placeholder is replaced with the corresponding value for that document

#### Scenario: A placeholder split across formatting runs is still substituted

- **WHEN** the template stores a placeholder as several adjacent text fragments (for example `{`, `url`, `}`) because of how Word saved it
- **THEN** the generator recognizes it as one placeholder and substitutes it
- **AND** the output contains no leftover brace characters from that placeholder

#### Scenario: A value is unavailable for a placeholder

- **WHEN** a document is generated and the source data has no value for one of the five placeholders
- **THEN** the placeholder is replaced with an empty string
- **AND** generation still succeeds

### Requirement: Each document type composes its body onto the shared template

Because the shared template carries no body, each document type SHALL generate its body content and insert it into the template's document body ahead of the section properties, preserving the template's page setup, margins, and styles.

#### Scenario: Main resume body is composed from structured resume data

- **WHEN** the main resume DOCX is generated for the current resume version
- **THEN** the output contains the summary, technical skills, professional experience, selected projects, and education sections drawn from that version's stored resume data
- **AND** the section ordering and heading structure match the resume the site displays

#### Scenario: Targeted resume body is composed from its tailored content

- **WHEN** a targeted resume DOCX is generated
- **THEN** the output body is rendered from that targeted resume's tailored content
- **AND** the header carries the targeted resume's title rather than the default resume title

#### Scenario: Cover letter body reproduces the letter structure

- **WHEN** a cover letter DOCX is generated
- **THEN** the output contains, in order: the letter date, the company address block, the greeting, the message body, the closing, and the typed signature name
- **AND** the message body's formatting (paragraphs, emphasis, bulleted and numbered lists) is preserved from the stored Markdown

#### Scenario: Page setup survives body composition

- **WHEN** any document type is generated
- **THEN** the output retains the shared template's page size, margins, and section properties

### Requirement: Generation fails loudly when the template cannot be used

The system SHALL treat a missing, unreadable, or structurally invalid shared template as a generation failure, returning an error that names the problem rather than writing a partial or corrupt document.

#### Scenario: Template file is missing

- **WHEN** a document is generated and the configured template path does not exist
- **THEN** generation returns a failure result identifying the missing template path
- **AND** no output file is left behind

#### Scenario: Template cannot be parsed

- **WHEN** a document is generated and the template cannot be opened as a valid Word document
- **THEN** generation returns a failure result describing the parse failure
- **AND** no output file is left behind

### Requirement: PDF generation continues from the generated DOCX

Each document type SHALL continue to produce its PDF by converting its generated DOCX, and SHALL report a conversion failure rather than leaving a stale PDF in place as if it were current.

#### Scenario: PDF follows a successful DOCX generation

- **WHEN** a document's DOCX has been generated and a PDF is requested
- **THEN** the PDF is produced from that DOCX and reflects the same content

#### Scenario: PDF requested with no DOCX present

- **WHEN** a PDF is requested for a document that has no generated DOCX
- **THEN** the request fails with an error stating the DOCX must be generated first

## REMOVED Requirements

### Requirement: Per-document-type DOCX templates

**Reason**: The main resume, targeted resume, and cover letter each rendered from their own template file (`2026 resume template.docx`, `2026 targeted resume template.docx`, `2026 cover letter template.docx`), which caused branding drift between the three documents and required three separate Word edits for any shared presentation change.

**Migration**: All three document types now read the single configured shared template (`resources/resume/2026 template.docx`). The three retired template files are deleted. The main resume's body, previously expressed as template loops inside `2026 resume template.docx`, is now composed from structured resume data at generation time; the cover letter's header part and logo image are not carried forward, and cover letters take the shared template's presentation instead.
