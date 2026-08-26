## ADDED Requirements

### Requirement: Manual markdown editor on the Targeted Resume Show page
The system SHALL provide a markdown editor for the finalized targeted resume's content on the admin Targeted Resume Show page, available only when a `TargetedResume` already exists for the conversation (i.e. it has been finalized at least once via chat).

#### Scenario: Editor available after a chat finalize
- **WHEN** an admin opens a targeted resume conversation that already has a finalized `TargetedResume`
- **THEN** the Show page displays an editor tab pre-populated with the current `tailored_data` markdown

#### Scenario: Editor unavailable before any finalize
- **WHEN** an admin opens a targeted resume conversation that has no `TargetedResume` yet
- **THEN** the manual-edit tab/action is not shown (or is disabled) because there is nothing to edit

### Requirement: Saving a manual edit persists content and regenerates artifacts
The system SHALL persist admin-edited markdown to the same `tailored_data` storage the chat "finalize" action uses, and SHALL regenerate the DOCX and PDF artifacts from the saved markdown using the existing document-generation path.

#### Scenario: Successful manual save
- **WHEN** an admin edits the markdown in the editor and saves
- **THEN** the system updates `tailored_data.markdown` (and `.content`) on the `TargetedResume`
- **AND** the system regenerates the DOCX and PDF files from the updated markdown
- **AND** the admin sees confirmation that the save succeeded

#### Scenario: Document regeneration fails after a manual save
- **WHEN** an admin saves edited markdown and DOCX/PDF regeneration fails
- **THEN** the edited markdown is still persisted
- **AND** the admin is shown the regeneration error instead of a silent failure

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
