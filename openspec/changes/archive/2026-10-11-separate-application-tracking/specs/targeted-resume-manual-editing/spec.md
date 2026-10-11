## RENAMED Requirements

- FROM: `### Requirement: Manual markdown editor on the Targeted Resume Show page`
- TO: `### Requirement: Manual markdown editor on the Edit Targeted Resume page`

## MODIFIED Requirements

### Requirement: Manual markdown editor on the Edit Targeted Resume page

The system SHALL provide a markdown editor for a targeted resume's content on a standalone Edit Targeted Resume page, separate from the Application Discussion page. The page SHALL be reachable from the Edit button inside the Resume card on the Application Discussion page, from the Resume column of the Applications list, and from the Targeted Resumes list. It SHALL exist only for a targeted resume that already exists (i.e. one finalized at least once via chat), SHALL show which application the resume belongs to with a link back to it, and SHALL offer the resume's download actions. The Application Discussion page SHALL NOT carry an editor tab.

#### Scenario: Editor available after a chat finalize

- **WHEN** an admin opens the Edit Targeted Resume page for an existing targeted resume
- **THEN** the page displays an editor pre-populated with the current `tailored_data` markdown
- **AND** it names the application's company and position and links back to the Application Discussion page

#### Scenario: Editor unavailable before any finalize

- **WHEN** an application has no targeted resume yet
- **THEN** its Resume card shows no Edit button and its Applications list row shows an em dash in the Resume column, because there is nothing to edit

#### Scenario: Edit button in the Resume card

- **WHEN** an admin clicks Edit in the Resume card on the Application Discussion page
- **THEN** the Edit Targeted Resume page opens for that application's targeted resume

#### Scenario: Unknown targeted resume

- **WHEN** an admin requests the Edit Targeted Resume page for a targeted resume that does not exist
- **THEN** the response is 404
