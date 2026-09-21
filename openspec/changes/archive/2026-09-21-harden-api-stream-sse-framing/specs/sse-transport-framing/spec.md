## ADDED Requirements

### Requirement: Frames are parsed on the true SSE boundary
`api.stream()` SHALL split the accumulated decoded buffer on a blank line (`\n\n`) to detect frame boundaries, rather than a single newline, and SHALL carry forward any incomplete trailing frame to be completed by a subsequent chunk read.

#### Scenario: A frame split across two chunk reads
- **WHEN** the response body delivers a complete SSE frame's bytes split across two separate `reader.read()` chunks
- **THEN** `stream()` yields exactly one payload for that frame, once the second chunk arrives, rather than yielding a truncated or malformed partial payload after the first chunk

#### Scenario: Multiple complete frames in one chunk
- **WHEN** a single chunk read contains two or more complete `data: {json}\n\n` frames
- **THEN** `stream()` yields one payload per frame, in order

### Requirement: The `data:` prefix is accepted with or without a trailing space
`api.stream()` SHALL recognize a line as an SSE data line when it starts with `data:`, and SHALL strip exactly the `data:` prefix (trimming any following whitespace), rather than requiring the literal `"data: "` (with space) prefix.

#### Scenario: A data line with no space after the colon
- **WHEN** a frame contains the line `data:{"type":"status"}`
- **THEN** `stream()` yields `{"type":"status"}` as the payload

#### Scenario: A data line with the conventional space
- **WHEN** a frame contains the line `data: {"type":"status"}`
- **THEN** `stream()` yields `{"type":"status"}` as the payload, identical to the no-space case

### Requirement: Multiple `data:` lines within one frame are joined into a single payload
`api.stream()` SHALL join every `data:` line within a single frame (in order, with each line's prefix stripped) into one payload string before yielding, rather than yielding each `data:` line as a separate payload.

#### Scenario: A frame with two data lines
- **WHEN** a frame contains `data: {"type":"content_block_` on one line and `data: delta","delta":{"text":"hi"}}` on the next, separated by a single newline within the same frame
- **THEN** `stream()` yields the single joined string `{"type":"content_block_delta","delta":{"text":"hi"}}`, not two separate (and individually unparseable) payloads

### Requirement: The stream is flushed and its final frame dispatched at end of stream
After the response body's read loop ends, `api.stream()` SHALL flush the `TextDecoder` (decoding any buffered partial multi-byte sequence with no further input) and, if the resulting buffer contains a non-blank frame, SHALL dispatch it as a final payload through the same `data:`-extraction and join logic as any other frame.

#### Scenario: Stream ends on a terminal error frame with no trailing separator
- **WHEN** the response body closes immediately after a `data: {"type":"error",...}` frame that is not followed by a trailing `\n\n` or a `[DONE]` sentinel
- **THEN** `stream()` still yields that frame's payload before the generator completes

#### Scenario: Stream ends cleanly after `[DONE]`
- **WHEN** the response body closes normally after a frame containing `data: [DONE]\n\n`
- **THEN** the end-of-stream flush finds nothing left to dispatch, and `stream()` completes without yielding an extra empty payload
