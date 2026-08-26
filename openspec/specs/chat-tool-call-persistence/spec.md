# chat-tool-call-persistence

## Purpose

Defines how the chat page's historical transcript carries tool call and tool result data alongside the role/content/reasoning/blocks data the package's `ChatBotPresenter::transcript()` already provides, how that data is redacted in production to match the live streaming behavior, and how the frontend renders that tool activity identically whether it arrived live or is read back from history.

## Requirements

### Requirement: Chat page transcript carries tool call and result data

The chat page payload SHALL include each historical message's tool call and tool result data (as already persisted by `jvjvjv/code-talker`'s `TurnRecorder` on `AiConversationMessage.tool_calls`/`tool_results`), in addition to the role/content/reasoning/blocks data the package's `ChatBotPresenter::transcript()` already provides. A message with no tool activity SHALL carry no tool data (empty/absent, not an empty-shaped placeholder).

#### Scenario: Historical message includes its tool activity

- **WHEN** a conversation's transcript is loaded for a turn where the assistant called a tool
- **THEN** that message's entry in the transcript includes the tool's name and, subject to the production redaction requirement below, its arguments and result

#### Scenario: Message without tool activity carries no tool data

- **WHEN** a conversation's transcript is loaded for a turn where the assistant used no tools
- **THEN** that message's entry in the transcript has no tool call or result data

### Requirement: Historical tool payloads are redacted in production exactly as the live stream is

Tool call arguments and tool result output SHALL be omitted from the transcript payload whenever the application is running in the `production` environment, leaving only each tool's name (and call/result identifiers needed to pair them). This SHALL match the existing redaction already applied to live `tool_use_progress` streaming frames, which are gated by `usingToolPayloads()` being called only outside `production`.

#### Scenario: Production request omits tool arguments and results

- **WHEN** the chat page transcript is built while the application environment is `production`
- **THEN** no tool call's `arguments` or tool result's `result`/`output` appear anywhere in the response, for any message

#### Scenario: Non-production request includes tool arguments and results

- **WHEN** the chat page transcript is built while the application environment is not `production`
- **THEN** a message with tool activity includes that tool call's arguments and its result, exactly as the live stream would have shown them for the same turn

### Requirement: Tool activity renders identically for live and historical messages

The frontend SHALL render a message's tool call/result activity using the same visual treatment (`ToolsPanel`) whether that activity is arriving live during an in-progress turn or is read back from a historical, already-persisted message. This SHALL NOT require any change to the package-published `MessageBlock` frontend contract (`resources/js/types/code-talker.d.ts`) — tool activity is carried as host-only data, the same way live tool activity already is.

#### Scenario: Tool activity from a just-finished turn remains visible

- **WHEN** a chat turn that called a tool finishes streaming
- **THEN** the newly-appended message in the visible conversation still shows that tool's activity, without requiring a page reload

#### Scenario: Tool activity survives a reload

- **WHEN** a chat page is reloaded after a previous turn called a tool
- **THEN** that turn's message renders its tool activity identically to how it appeared while streaming (subject to the production redaction requirement)

#### Scenario: Reopening an older conversation shows its tool activity

- **WHEN** a visitor switches to a previously-started conversation whose history includes a tool-using turn
- **THEN** that turn's tool activity renders in the loaded transcript

### Requirement: A tool call without a matching result renders as still in progress

When a persisted message's tool call has no corresponding entry in its tool results (e.g. the turn was cut off mid-tool-use by the max-duration guard), the tool block SHALL render using the same "in progress" visual state `ToolsPanel` already uses for a live, not-yet-resolved tool call, rather than being omitted or rendered as an error.

#### Scenario: Unresolved historical tool call shows as in progress

- **WHEN** a historical message's `tool_calls` contains a call whose id has no matching entry in `tool_results`
- **THEN** that tool's block renders in the same "in progress" state as an active live tool call
