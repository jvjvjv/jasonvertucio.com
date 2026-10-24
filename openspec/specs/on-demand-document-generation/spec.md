# on-demand-document-generation Specification

## Purpose

Defines when a generated document's DOCX or PDF is actually rendered — at the moment it is first requested for download, not when the underlying data is saved or approved — and how a rendered file is retained, invalidated, and logged afterward.

## Requirements

### Requirement: A document is rendered on first download request, not on save

The system SHALL NOT render a document's DOCX or PDF when its underlying data is saved or approved. The system SHALL render a document's DOCX or PDF the first time that specific format is requested for download after the underlying data last changed, if no currently-valid rendered file already exists.

#### Scenario: Saving data does not trigger a render

- **WHEN** a resume version is approved, a targeted resume is saved, or a cover letter is saved
- **THEN** no DOCX or PDF is rendered as part of that save
- **AND** the save completes without waiting on document rendering

#### Scenario: A download with no cached file triggers a render

- **WHEN** a document's DOCX or PDF is requested for download
- **AND** no currently-valid rendered file exists for that document and format
- **THEN** the system renders the requested format before serving it
- **AND** the rendered file reflects the document's current stored data

#### Scenario: A download with a cached file skips rendering

- **WHEN** a document's DOCX or PDF is requested for download
- **AND** a currently-valid rendered file already exists for that document and format
- **THEN** the system serves the existing file without re-rendering it

#### Scenario: DOCX and PDF are rendered independently

- **WHEN** a document's DOCX is requested while no PDF has ever been rendered for it, or vice versa
- **THEN** only the requested format is rendered
- **AND** the other format remains unrendered until it is itself requested

### Requirement: Saving or approving invalidates previously rendered documents

Because rendering no longer happens on save, a save or approval that changes a document's underlying data SHALL invalidate any DOCX or PDF file currently associated with it, so a later download cannot be served stale content rendered from data that has since changed.

#### Scenario: A save invalidates its own document's cached files

- **WHEN** a targeted resume's tailored content is saved, or a cover letter's fields are saved
- **THEN** any DOCX or PDF file previously rendered for that document is no longer served
- **AND** the next download of either format renders fresh from the newly saved data

#### Scenario: Approving a resume candidate invalidates the new version's documents

- **WHEN** a pending resume edit candidate is approved and published as the new current resume version
- **THEN** the new version has no DOCX or PDF considered currently valid
- **AND** the next download of either format for that version renders fresh from the approved data

### Requirement: A rendered document's retention is configurable

The system SHALL support two retention modes for a rendered DOCX or PDF, selected by configuration and applied the same way across the main resume, targeted resumes, and cover letters: retaining it for a configurable duration after rendering, or deleting it immediately once it has been served.

#### Scenario: Cache mode retains a rendered file for its configured window

- **WHEN** the system is configured in the time-window retention mode
- **AND** a document's DOCX or PDF is rendered
- **THEN** the rendered file remains valid and reusable by later downloads until the configured retention duration has elapsed since it was rendered

#### Scenario: A cached file past its retention window is no longer served

- **WHEN** the system is configured in the time-window retention mode
- **AND** a rendered file's retention duration has elapsed since it was rendered
- **THEN** the file is no longer treated as currently valid
- **AND** the next download of that document and format renders a fresh file

#### Scenario: Delete-after-serve mode removes a file once it has been downloaded

- **WHEN** the system is configured in the delete-after-serve retention mode
- **AND** a rendered document's DOCX or PDF finishes being served to a downloader
- **THEN** the served file is deleted
- **AND** the next download of that document and format renders a fresh file, regardless of how soon after it arrives

### Requirement: Every document download is logged

The system SHALL record a log entry for every completed download of a generated document — the main resume, a targeted resume, or a cover letter — identifying which document was downloaded, which format, the downloader's IP address, and whether the download was served from an already-rendered file or triggered a fresh render.

#### Scenario: A cache-hit download is logged as served from cache

- **WHEN** a document download is served from an existing rendered file without triggering a render
- **THEN** a log entry is recorded for that download identifying the document, the format, and the downloader's IP address
- **AND** the entry records that it was served from a cached file

#### Scenario: A cache-miss download is logged as freshly rendered

- **WHEN** a document download triggers a render because no currently-valid file existed
- **THEN** a log entry is recorded for that download identifying the document, the format, and the downloader's IP address
- **AND** the entry records that it was not served from a cached file

#### Scenario: Downloads of all three document types are logged the same way

- **WHEN** the main resume, a targeted resume, and a cover letter are each downloaded
- **THEN** each download produces its own log entry identifying which one of the three documents it was

### Requirement: A download-time rendering failure is reported, not served as broken or stale content

If rendering a document at download time fails, the system SHALL report the failure to the requester rather than serving a partial, corrupt, or previously-cached-but-now-invalid file as if it reflected current data.

#### Scenario: A render triggered by a download fails

- **WHEN** a download request triggers a render because no currently-valid file existed
- **AND** that render fails
- **THEN** the download request fails with an error describing the problem
- **AND** no partial or corrupt file is served or left in place

#### Scenario: A failed render does not fall back to stale content

- **WHEN** a download request triggers a render because the previously rendered file was invalidated or expired
- **AND** the new render fails
- **THEN** the invalidated file is not served in its place

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
