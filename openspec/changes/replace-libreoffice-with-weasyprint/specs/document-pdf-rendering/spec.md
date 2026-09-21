## Purpose

Defines how the site produces the PDF of every document it generates — the main resume, targeted resumes and cover letters — by rendering each one from the same body content and the same template styling its DOCX is built from, so the two formats describe the same document without either depending on the other.

## ADDED Requirements

### Requirement: A document's PDF is rendered from its own body source

The system SHALL produce a document's PDF from the same body content its DOCX is composed from, rather than by converting the generated DOCX. A PDF SHALL be producible for a document whose DOCX has never been generated.

#### Scenario: PDF is produced with no DOCX present

- **WHEN** a PDF is requested for a document that has no generated DOCX
- **THEN** the PDF is produced
- **AND** the request does not fail on the absence of a DOCX

#### Scenario: PDF and DOCX describe the same document

- **WHEN** a document's DOCX and PDF are both generated from the same stored content
- **THEN** both contain the same sections, in the same order, with the same text
- **AND** neither reports content the other omits

#### Scenario: A stale DOCX does not affect the PDF

- **WHEN** a document's stored content changes and only its PDF is regenerated
- **THEN** the PDF reflects the changed content
- **AND** the previously generated DOCX is neither read nor required

#### Scenario: Line breaking may differ between the two formats

- **WHEN** a document's DOCX and PDF are compared
- **THEN** the two MAY break lines at different points within a paragraph
- **AND** each section still begins on the same page in both

### Requirement: The shared template governs the PDF's appearance

The PDF SHALL take its page size, margins, typefaces, colors and paragraph styling from the same shared template the DOCX is rendered from. Editing that template SHALL change the PDF's appearance without any other file being edited and without any separate command being run.

#### Scenario: A template style edit reaches the PDF

- **WHEN** a paragraph style's size, color or spacing is changed in the shared template
- **AND** a document's PDF is regenerated
- **THEN** the PDF reflects the change

#### Scenario: A page setup edit reaches the PDF

- **WHEN** the shared template's page size or margins are changed
- **AND** a document's PDF is regenerated
- **THEN** the PDF uses the new page size and margins

#### Scenario: The letterhead matches the DOCX

- **WHEN** a document's PDF is generated
- **THEN** its name line and contact line carry the same text, styling and placeholder substitutions as the DOCX's
- **AND** no literal placeholder text appears in the PDF

#### Scenario: The design has one home

- **WHEN** the shared template is the only file edited
- **THEN** no second stylesheet requires a matching edit for the PDF to stay consistent with the DOCX

### Requirement: The PDF embeds the template's typefaces

The PDF SHALL embed the same typefaces the shared template specifies, at the weights and styles the template's paragraph styles call for. The system SHALL NOT substitute a different family, synthesize a weight it failed to embed, or embed a variable font's default instance in place of a requested weight.

#### Scenario: Every required face is embedded

- **WHEN** a generated PDF's embedded fonts are inspected
- **THEN** each typeface the document's styles call for is present and embedded
- **AND** no text is rendered in a substituted fallback family

#### Scenario: Bold text uses a real bold face

- **WHEN** a document containing bold body text is rendered
- **THEN** the bold text uses an embedded bold face
- **AND** the PDF contains no synthesized (stroke-filled) bold

#### Scenario: No weight collapses to a variable font default

- **WHEN** a generated PDF's embedded faces are inspected
- **THEN** no face intended as regular, bold or italic is embedded as a thin or other unintended weight

### Requirement: The skills section's column behavior matches the DOCX

A resume PDF SHALL lay its skills section out the same way the DOCX does: a marked leading group across the full page width, the remainder flowing in two columns, and the whole section in two columns when no division is marked.

#### Scenario: A resume with both top and other skill categories

- **WHEN** a resume PDF is rendered from content that marks a division between leading and remaining skill categories
- **THEN** the leading categories occupy the full page width
- **AND** the remaining categories flow in two columns

#### Scenario: Content that marks no division

- **WHEN** a resume PDF is rendered from content whose skill categories are listed without a marked division
- **THEN** the entire skills section flows in two columns

#### Scenario: A column region taller than one page

- **WHEN** the two-column skills region contains more content than fits on the page it begins on
- **THEN** the region continues onto the following page
- **AND** the remaining content is distributed across both columns on that page
- **AND** no skill category is split across a column or page boundary
- **AND** no content is lost

#### Scenario: Sections after the skills section return to full width

- **WHEN** a resume PDF is rendered with a skills section followed by further sections
- **THEN** every section after the skills section occupies the full page width

### Requirement: Section headings print their display labels

A resume PDF SHALL print the same section headings the DOCX and the site's resume page print, and SHALL render the summary without a heading.

#### Scenario: Headings read the same across formats

- **WHEN** a resume PDF is generated
- **THEN** the skills heading reads "Technical Skills", the experience heading reads "Professional Experience", the projects heading reads "Selected Projects", and the education heading reads "Education"
- **AND** no heading reads a bare section identifier
- **AND** the summary appears with no heading above it

### Requirement: The cover letter's signature is written across the sign-off

A cover letter PDF SHALL place the signature image so that it overlaps the closing and the typed name, in the brand color, with its background transparent — reproducing the placement the DOCX uses. A signature that cannot be produced SHALL NOT prevent the letter from being rendered.

#### Scenario: The signature overlaps the sign-off

- **WHEN** a cover letter PDF is rendered
- **THEN** the signature is drawn across the closing and the typed name rather than occupying a line of its own
- **AND** the closing and the typed name remain legible beneath it

#### Scenario: The signature's background does not obscure the text

- **WHEN** a cover letter PDF is rendered
- **THEN** the area around the signature's strokes is transparent
- **AND** no opaque rectangle covers the surrounding text

#### Scenario: The signature is rendered in the brand color

- **WHEN** a cover letter PDF is rendered
- **THEN** the signature's visible strokes are the brand color rather than the source image's own color

#### Scenario: The signature cannot be produced

- **WHEN** the signature image is missing or cannot be read
- **THEN** the cover letter PDF is still rendered, without a signature
- **AND** the failure is logged

#### Scenario: The sign-off is not orphaned

- **WHEN** a cover letter's sign-off does not fit in what remains of the page
- **THEN** the closing, the signature and the typed name move to the next page together

### Requirement: A rendering failure is reported and leaves no partial output

The system SHALL report a PDF rendering failure with an error describing the problem, and SHALL NOT leave a partial, corrupt or stale PDF in place as if it were current.

#### Scenario: Rendering fails

- **WHEN** a document's PDF cannot be rendered
- **THEN** the caller receives a failure result describing the problem
- **AND** no partial PDF file is left behind

#### Scenario: Rendering fails with a previous PDF already on disk

- **WHEN** a document already has a generated PDF and a regeneration fails
- **THEN** the failure is reported
- **AND** the previous PDF is not presented as reflecting the current content

#### Scenario: The renderer is unavailable

- **WHEN** the PDF renderer is not installed or cannot be executed
- **THEN** the failure result names that as the cause
- **AND** DOCX generation is unaffected

#### Scenario: Rendering does not hang indefinitely

- **WHEN** a render does not complete within its configured time limit
- **THEN** it is abandoned and reported as a failure
- **AND** no partial PDF file is left behind
