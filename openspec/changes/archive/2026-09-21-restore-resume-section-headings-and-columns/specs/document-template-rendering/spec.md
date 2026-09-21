## MODIFIED Requirements

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

#### Scenario: Section headings carry their display labels

- **WHEN** a resume document — main or targeted — is generated from body content whose sections are identified as summary, skills, experience, projects, and education
- **THEN** the skills section heading reads "Technical Skills", the experience section heading reads "Professional Experience", the projects section heading reads "Selected Projects", and the education section heading reads "Education"
- **AND** no heading in the output reads the bare section identifier "Skills", "Experience", or "Projects"
- **AND** the summary section is rendered without any heading

#### Scenario: The same labels appear on the site's resume page

- **WHEN** the site's resume page is viewed and the generated resume document is opened
- **THEN** the four section headings read the same on both surfaces

## ADDED Requirements

### Requirement: A section's display label does not change how its content is styled

The identifier that selects a section's paragraph styling SHALL be independent of the label printed for that section, so that the label can be changed without altering the styling contract that stored and model-generated body content relies on.

#### Scenario: Body content using bare section identifiers still renders styled

- **WHEN** body content identifies its sections by the bare identifiers "Skills", "Experience", "Projects", "Education" — as previously stored targeted resumes and the targeted-resume agent's output do
- **THEN** each section renders with its section-specific paragraph styling (job titles and company lines in the experience section, column flow in the skills section)
- **AND** each section heading prints its display label

#### Scenario: Body content using display labels still renders styled

- **WHEN** body content identifies a section by its display label instead — for example a hand-edited targeted resume whose experience section is headed "Professional Experience"
- **THEN** that section renders with the same section-specific paragraph styling as the bare identifier would produce
- **AND** the heading is not duplicated or relabeled

### Requirement: Top skills run full width and the remaining categories flow in two columns

A resume's skill categories MAY be divided into a leading emphasized group and a remaining group. When a resume's body content marks that division, the generated document SHALL render the leading group across the full page width and flow the remaining group in two columns. When no division is marked, the whole skills section SHALL flow in two columns.

#### Scenario: A resume with both top and other skill categories

- **WHEN** the main resume is generated for resume data that has both top skill categories and other skill categories
- **THEN** the top categories occupy the full page width
- **AND** the remaining categories flow in two columns
- **AND** this matches how the site's resume page presents the two groups

#### Scenario: A resume with no top skill categories

- **WHEN** the main resume is generated for resume data whose skills are all in the remaining group
- **THEN** the entire skills section flows in two columns

#### Scenario: A resume with only top skill categories

- **WHEN** the main resume is generated for resume data whose skills are all in the top group
- **THEN** the entire skills section occupies the full page width
- **AND** no empty two-column region is left in the document

#### Scenario: Body content that marks no division

- **WHEN** a targeted resume is generated from stored tailored content that lists its skill categories without marking a division
- **THEN** the entire skills section flows in two columns

### Requirement: Column regions govern the content they enclose

A multi-column region in a generated document SHALL contain the content it is meant to lay out. The generator SHALL NOT emit a column region that encloses no content while the content it was meant to govern falls outside it.

#### Scenario: The skills content is inside the two-column region

- **WHEN** a document containing a two-column skills region is generated and its layout regions are inspected
- **THEN** the skill category headings and skill lists intended for two columns are inside the two-column region
- **AND** the two-column region is not empty

#### Scenario: Sections after the skills section return to full width

- **WHEN** a resume document is generated with a skills section followed by an experience section
- **THEN** the experience section and every section after it occupy the full page width
- **AND** no content after the skills section is left in a two-column region
