## ADDED Requirements

### Requirement: Chat streaming and status logic lives in reusable hooks
Stateful, non-presentational chat logic (SSE stream parsing and message state, model-status polling/warmup, and page-level URL sync) SHALL live in dedicated hooks under `resources/js/hooks/`, not inline inside page or component files.

#### Scenario: Streaming logic is not duplicated across chat surfaces
- **WHEN** a new chat surface needs to send a message and parse a streamed response
- **THEN** it SHALL be able to reuse `useChatStream` from `resources/js/hooks/` instead of re-implementing SSE parsing

#### Scenario: Model status polling has a single implementation
- **WHEN** `ChatInterface` needs to check or warm up the model status on mount
- **THEN** it SHALL delegate to `useModelStatus` from `resources/js/hooks/` rather than inlining the check/warmup effects

### Requirement: Single-use presentational pieces are colocated in a subdirectory named after their source file
When a large component or page is split into single-use sub-components (used by only that one parent), the sub-components SHALL live in a subdirectory colocated with the parent file, named as the kebab-case of the parent file's base name.

#### Scenario: ChatInterface sub-components are colocated
- **WHEN** a presentational piece is extracted out of `resources/js/components/ChatInterface.tsx` (e.g. the empty-state placeholder or the virtualized list renderer) and is not reused elsewhere
- **THEN** it SHALL be placed under `resources/js/components/chat-interface/` as its own file

#### Scenario: ChatMessageBubble sub-components are colocated
- **WHEN** a rendering branch is extracted out of `resources/js/components/ChatMessageBubble.tsx` (e.g. the block-based renderer or the legacy content renderer)
- **THEN** it SHALL be placed under `resources/js/components/chat-message-bubble/` as its own file

#### Scenario: ChatHistoryPanel cards are colocated
- **WHEN** one of `ChatHistoryPanel.tsx`'s three cards (chat list, bot access info, prompt notes) is extracted into its own component
- **THEN** it SHALL be placed under `resources/js/chat/pages/ai/chat-history-panel/` as its own file

### Requirement: Public component APIs are preserved across the split
Extracting a large component into hooks and sub-files SHALL NOT change that component's exported props, ref handle shape, or exported TypeScript types, so existing consumers require no logic changes.

#### Scenario: ChatInterface consumers are unaffected
- **WHEN** `ChatInterface.tsx` is decomposed into hooks and colocated sub-components
- **THEN** `ChatInterfaceProps`, `ChatInterfaceHandle`, `ChatMessage`, `ModelStatus`, `StreamEvent`, and `MessageBlock` SHALL remain importable from their original module paths with unchanged shapes

#### Scenario: Existing consumers require no prop changes
- **WHEN** `BuilderChatPanel.tsx`, `ChatBot.tsx`, or `admin/pages/ai/conversations/Show.tsx` render or import from the refactored files after the change
- **THEN** they SHALL continue to compile and render without any changes to the props or values they pass
