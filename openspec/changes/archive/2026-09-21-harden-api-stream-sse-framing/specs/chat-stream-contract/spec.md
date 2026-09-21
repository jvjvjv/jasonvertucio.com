## MODIFIED Requirements

### Requirement: The app keeps its own stream transport

The chat stream SHALL continue to be read through `api.stream`, which retries once on HTTP 419 after refreshing the CSRF token, fires the session-expiry and activity hooks, and yields every frame to its caller. The app SHALL NOT replace this with the package's published `streamChatTurn` client while that client drops unrecognized event types.

#### Scenario: A session that expires mid-send recovers

- **WHEN** a message POST returns 419
- **THEN** the CSRF token is refreshed, the send is retried once, and the session-expiry handler fires

#### Scenario: Host events reach the hook

- **WHEN** the server emits `page_reload` or `tool_use_progress`
- **THEN** the frame is delivered to `useChatStream` and passed to `onEvent`, rather than being filtered out by the transport

#### Scenario: The transport's own framing is spec'd separately

- **WHEN** `api.stream` parses SSE bytes into frames and payload strings
- **THEN** its boundary detection, `data:` prefix handling, multi-line joining, and end-of-stream flush behavior are governed by the `sse-transport-framing` capability, not restated here
