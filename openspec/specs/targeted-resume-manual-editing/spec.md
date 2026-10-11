# targeted-resume-manual-editing

## Purpose

Lets an admin manually edit a finalized targeted resume's markdown from the admin Targeted Resume Show page, outside the chat flow, while keeping the chat agent aware of the edit and regenerating the downloadable artifacts from the edited content.

## Requirements

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

### Requirement: Manual edits notify the chat agent via conversation history

The system SHALL record every manual markdown save as a message in the same conversation's message history (`AiConversationMessage`) so that the chat agent has access to the edit as context on its next turn, without triggering an immediate agent response.

#### Scenario: Manual edit appends a conversation message

- **WHEN** an admin saves a manual markdown edit
- **THEN** the system creates a new `user`-role `AiConversationMessage` on the associated conversation summarizing that the resume was edited outside of chat, including the updated markdown content
- **AND** the message is tagged with `metadata.origin = "manual_edit"`
- **AND** no agent turn (LLM call) is triggered synchronously by this save

#### Scenario: Agent sees the manual edit on its next turn

- **WHEN** the admin next sends a chat message in the same conversation after a manual edit
- **THEN** the agent's message history includes the manual-edit message, so its reply is grounded in the edited content

### Requirement: Manual-edit messages are visually distinguished in the chat transcript

The system SHALL render conversation messages tagged with `metadata.origin = "manual_edit"` distinctly from ordinary chat messages in the transcript UI.

#### Scenario: Manual-edit message rendered with a distinct indicator

- **WHEN** the chat transcript renders a message with `metadata.origin = "manual_edit"`
- **THEN** it is displayed with a visual indicator (e.g. icon/label) distinguishing it from a message the admin typed directly into chat
