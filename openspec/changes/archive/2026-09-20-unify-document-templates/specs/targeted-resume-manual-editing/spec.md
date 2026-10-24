## MODIFIED Requirements

### Requirement: Saving a manual edit persists content and regenerates artifacts

The system SHALL persist admin-edited markdown to the same `tailored_data` storage the chat "finalize" action uses, and SHALL regenerate the DOCX and PDF artifacts from the saved markdown using the shared document-generation path — the same configured template and body-composition behavior every generated document uses.

#### Scenario: Successful manual save

- **WHEN** an admin edits the markdown in the editor and saves
- **THEN** the system updates `tailored_data.markdown` (and `.content`) on the `TargetedResume`
- **AND** the system regenerates the DOCX and PDF files from the updated markdown
- **AND** the regenerated DOCX is rendered from the shared configured template
- **AND** the admin sees confirmation that the save succeeded

#### Scenario: Document regeneration fails after a manual save

- **WHEN** an admin saves edited markdown and DOCX/PDF regeneration fails
- **THEN** the edited markdown is still persisted
- **AND** the admin is shown the regeneration error instead of a silent failure

#### Scenario: Regenerated targeted resume carries no signature

- **WHEN** an admin saves edited markdown and the DOCX is regenerated
- **THEN** the regenerated document contains no signature image, which belongs to cover letters only
