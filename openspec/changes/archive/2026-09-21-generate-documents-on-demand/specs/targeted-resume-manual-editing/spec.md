## MODIFIED Requirements

### Requirement: Saving a manual edit persists content and regenerates artifacts

The system SHALL persist admin-edited markdown to the same `tailored_data` storage the chat "finalize" action uses, and SHALL invalidate any DOCX or PDF previously rendered for that targeted resume, so a later download renders fresh from the saved markdown (per `on-demand-document-generation`) rather than serving content rendered before the edit.

#### Scenario: Successful manual save

- **WHEN** an admin edits the markdown in the editor and saves
- **THEN** the system updates `tailored_data.markdown` (and `.content`) on the `TargetedResume`
- **AND** any DOCX or PDF previously rendered for that targeted resume is no longer served
- **AND** the admin sees confirmation that the save succeeded

#### Scenario: Document regeneration fails after a manual save

- **WHEN** an admin saves edited markdown
- **THEN** the save never renders a DOCX or PDF, so there is no synchronous rendering step for the save to depend on or be failed by
- **AND** the edited markdown is persisted regardless
- **AND** a rendering problem, if one ever occurs, can only surface later, at the next download (per `on-demand-document-generation`), not as part of the save

#### Scenario: Regenerated targeted resume carries no signature

- **WHEN** a targeted resume's DOCX is rendered, whether at a later download following a manual save or otherwise
- **THEN** the rendered document contains no signature image, which belongs to cover letters only
