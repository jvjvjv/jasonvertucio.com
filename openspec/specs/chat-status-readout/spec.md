# chat-status-readout

## Purpose

The chat's single place for saying what it is doing: a 3px rail along the composer's top edge whose colour and motion give the state at a glance, and a caption line beneath the composer that says it in words, covering model readiness, the progress of a reply, dictation, warnings, errors and session expiry.

## Requirements

### Requirement: One status readout replaces the alert stack

The chat SHALL report its state through one readout made of the composer's status rail and the caption line beneath the composer. It SHALL NOT render status as full-width alerts between the transcript and the composer, as a separate session-expiry banner, or as a progress bar inside the streaming reply.

#### Scenario: No alerts on load

- **WHEN** a chat page loads and its model status check succeeds with the model loaded
- **THEN** no alert is shown above the composer, the rail is in the ready state, and the readout reports that the chat is ready

#### Scenario: No progress bar in the reply

- **WHEN** a reply is streaming
- **THEN** the streaming reply shows no progress bar, and the rail and caption report the reply's progress

### Requirement: Readout states and their wording

The readout SHALL show exactly one state at a time: the rail in the state's colour and the caption in words. When more than one condition holds, the first in this order SHALL be shown:

1. Session expired: "Your session has expired." in the warning colour with a warning rail, and a Refresh control in the action margin in place of Microphone and Send.
2. Dictation: while recording, the elapsed and maximum time and that Esc cancels, led by a recording dot; while transcribing, that the recording is being transcribed; after a dictation problem, the problem's message in the error colour with an error rail.
3. Reply failed: the stream's error message in full, in the error colour with an error rail.
4. Reply in progress, judged by the newest thing the turn has streamed: a status message sent by the server for this turn when one is current; otherwise that the reply is being written when reply text is newest; otherwise "Thinking" when reasoning is newest; otherwise "Running" and the tool's name when a tool call is newest and has no result yet; otherwise "Thinking". Each reply-in-progress state also says that Esc stops the reply.
5. Provider unavailable: that the provider is unavailable and sending is off, in the error colour with an error rail.
6. Checking the model: "Checking the model".
7. Waking the model: the warm-up message, defaulting to saying the model is waking and the first reply can take a minute.
8. Model not loaded or warm-up failed: the status message from the server, in the warning colour with a warning rail.
9. Ready: a primary-colour rail; the caption shows the model's name when the viewer is signed in and the status names a model, and otherwise shows no words. The state is announced as "Ready", followed by "on" and the model's name when it is shown.

#### Scenario: A tool is running

- **WHEN** a reply is streaming and its newest tool call, `search_company_news`, has not returned a result
- **THEN** the caption says "Running search_company_news" and the rail shows a travelling segment

#### Scenario: Text starts streaming after a tool

- **WHEN** the tool call returns and reply text begins to stream
- **THEN** the caption changes to say the reply is being written

#### Scenario: Server status frame takes precedence

- **WHEN** the server sends a status frame with the message "Waiting for model response" during a turn
- **THEN** the caption shows "Waiting for model response" until reply text or reasoning starts streaming

#### Scenario: Reply failure is shown in full

- **WHEN** a reply ends with the error "Upstream request timed out after 120 seconds."
- **THEN** the rail turns the error colour and holds still, the caption shows that message in full in the error colour, wrapping onto more lines if needed, and both clear when the next message is sent

#### Scenario: Session expiry outranks everything

- **WHEN** the session has expired while the model is ready
- **THEN** the caption says "Your session has expired." and a Refresh control in the action margin reloads the page

#### Scenario: Guests do not see the model name

- **WHEN** a visitor who is not signed in opens a public chat whose model is ready
- **THEN** the caption shows no model name and the readout is announced as "Ready"

#### Scenario: Signed-in owner sees the model name

- **WHEN** a signed-in user opens a chat whose status reports the model `qwen3:32b` as loaded
- **THEN** the caption shows "qwen3:32b" and the readout is announced as "Ready on qwen3:32b"

### Requirement: Ready is quiet and nothing else is hidden

The ready state SHALL be the readout's least prominent state: a still primary-colour rail and, at most, the model's name in muted text. Warning and error captions SHALL wrap across the full width of the caption line instead of truncating. Other captions SHALL stay on one line and truncate with an ellipsis when the composer is too narrow.

#### Scenario: Error wraps at phone width

- **WHEN** a reply fails with a long error message at a 360 pixel viewport
- **THEN** the whole message is readable in the caption line, wrapped across lines, with the composer's buttons still visible

#### Scenario: In-progress text truncates at phone width

- **WHEN** a tool with a long name is running at a 360 pixel viewport
- **THEN** the caption stays on one line, ending in an ellipsis

### Requirement: The caption shows the send shortcut when the chat is ready

At or above the small breakpoint, in the ready state and when no status is known yet, the caption line SHALL show the send shortcut on its right: "⌘+Enter sends" on Apple platforms and "Ctrl+Enter sends" elsewhere. It SHALL NOT be shown in any other state or below the small breakpoint.

#### Scenario: Ready on a desktop

- **WHEN** a signed-in user on Windows opens a ready chat at 1280 pixels
- **THEN** the caption shows the model's name on the left and "Ctrl+Enter sends" on the right

#### Scenario: Hidden while a reply streams

- **WHEN** a reply is streaming
- **THEN** the caption shows the reply's progress and no shortcut

#### Scenario: Hidden on a phone

- **WHEN** a ready chat is shown at 360 pixels
- **THEN** no shortcut is shown

### Requirement: The rail moves only while work is under way

The rail SHALL show a travelling segment only in the waking, reply-in-progress, recording and transcribing states. In every other state it SHALL be still. When the viewer prefers reduced motion, the segment SHALL be still in the middle of the rail in every state that would otherwise move it.

#### Scenario: Still when ready

- **WHEN** the readout shows the ready state
- **THEN** the rail is one still colour with no segment

#### Scenario: Reduced motion

- **WHEN** the viewer's system requests reduced motion and a reply is streaming
- **THEN** the rail's segment sits still in the middle of the rail, and the caption still says the reply is in progress

### Requirement: The readout is announced and never relies on colour alone

The caption SHALL be exposed to assistive technology as a polite live status region, and failures (reply failed, provider unavailable, dictation problem) SHALL be announced assertively. The rail SHALL be hidden from assistive technology. Every state SHALL carry its meaning in words, so the rail's colour is never the only signal.

#### Scenario: Screen reader hears the change of state

- **WHEN** a reply moves from "Thinking" to running a tool
- **THEN** a screen reader announces the new caption text without moving focus

#### Scenario: Failure is announced

- **WHEN** a reply fails
- **THEN** the error message is announced assertively
