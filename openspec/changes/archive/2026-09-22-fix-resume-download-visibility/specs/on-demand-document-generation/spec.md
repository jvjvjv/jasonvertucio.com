## ADDED Requirements

### Requirement: A download affordance is shown based on document existence, not rendered-file existence

The system SHALL present a download link or button for a document's DOCX and PDF formats whenever that document itself exists in a downloadable state — a live resume version exists, a targeted resume has been finalized, or a cover letter has been finalized — regardless of whether a DOCX or PDF has already been rendered and cached for it. The system SHALL NOT hide, omit, or disable a download affordance solely because no currently-valid rendered file exists yet for that format.

#### Scenario: The resume page shows download options with no cached file

- **WHEN** a visitor with download permission views the resume page
- **AND** a live resume version exists
- **AND** neither a DOCX nor a PDF has ever been rendered for that version
- **THEN** the page presents both a DOCX download option and a PDF download option
- **AND** selecting either one renders the file on demand and serves it

#### Scenario: The standalone download page shows download options with no cached file

- **WHEN** a visitor with download permission opens the standalone resume download page
- **AND** a live resume version exists
- **AND** neither a DOCX nor a PDF has ever been rendered for that version
- **THEN** the page presents both a DOCX download option and a PDF download option, rather than reporting no files are available

#### Scenario: A finalized targeted resume shows download options with no cached file

- **WHEN** an admin views a targeted resume that has been finalized in the chat interface
- **AND** neither a DOCX nor a PDF has ever been rendered for that targeted resume
- **THEN** the interface presents both a DOCX download option and a PDF download option for the targeted resume

#### Scenario: A finalized cover letter shows download options with no cached file

- **WHEN** an admin views a cover letter that has been finalized in the chat interface
- **AND** neither a DOCX nor a PDF has ever been rendered for that cover letter
- **THEN** the interface presents both a DOCX download option and a PDF download option for the cover letter

#### Scenario: A document that does not yet exist shows no download affordance

- **WHEN** no live resume version exists, or a targeted resume or cover letter has not yet been finalized
- **THEN** no download affordance is presented for that document

#### Scenario: A rendering failure at download time is still reported as a failure

- **WHEN** a visitor selects a download option for a format that has no cached file
- **AND** rendering that format on demand fails
- **THEN** the failure is reported to the visitor the same way an on-demand render failure is already reported
- **AND** the download affordance remains visible for a later attempt
