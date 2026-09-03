## MODIFIED Requirements

### Requirement: Historical tool payloads are redacted in production exactly as the live stream is

Tool call arguments and tool result output SHALL be omitted from the transcript payload unless the requesting user both holds the `manage-ai-tools` permission and has their `show_tool_payloads` preference enabled, leaving only each tool's name (and call/result identifiers needed to pair them) for everyone else. This SHALL match the redaction already applied to live `tool_use_progress` streaming frames, gated the same way. A guest visitor (no authenticated user) is always redacted, in every environment.

#### Scenario: Unpermitted or opted-out request omits tool arguments and results

- **WHEN** the chat page transcript is built for a guest, for a user without `manage-ai-tools`, or for a user with `manage-ai-tools` but `show_tool_payloads` disabled
- **THEN** no tool call's `arguments` or tool result's `result`/`output` appear anywhere in the response, for any message

#### Scenario: Permitted and opted-in request includes tool arguments and results

- **WHEN** the chat page transcript is built for a user who holds `manage-ai-tools` and has `show_tool_payloads` enabled
- **THEN** a message with tool activity includes that tool call's arguments and its result, exactly as the live stream would have shown them for the same turn, regardless of application environment

#### Scenario: Revoking the permission hides payloads on the next request

- **WHEN** a user with `show_tool_payloads` enabled has their `manage-ai-tools` permission revoked, then requests the chat page transcript again
- **THEN** no tool call's `arguments` or tool result's `result`/`output` appear in that response, even though `show_tool_payloads` is still stored as enabled for that user
