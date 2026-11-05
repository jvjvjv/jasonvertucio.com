## Purpose

The chat's single place for saying what it is doing: one short sentence in the composer's toolbar, led by a coloured notch, covering model readiness, the progress of a reply, dictation, warnings, errors and session expiry.

## ADDED Requirements

### Requirement: One status readout replaces the alert stack

The chat SHALL report its state through one readout in the composer's toolbar row. It SHALL NOT render status as full-width alerts between the transcript and the composer, as a separate session-expiry banner, or as a progress bar inside the streaming reply.

#### Scenario: No alerts on load

- **WHEN** a chat page loads and its model status check succeeds with the model loaded
- **THEN** no alert is shown above the composer and the readout reports that the chat is ready

#### Scenario: No progress bar in the reply

- **WHEN** a reply is streaming
- **THEN** the streaming reply shows no progress bar and the readout reports the reply's progress

### Requirement: Readout states and their wording

The readout SHALL show exactly one state at a time, as words led by a notch whose colour matches the state. When more than one condition holds, the first in this order SHALL be shown:

1. Session expired: "Your session has expired." in the warning colour, with a Refresh control in place of the composer's buttons.
2. Dictation: while recording, the elapsed and maximum time and that Esc cancels; while transcribing, that the recording is being transcribed; after a dictation problem, the problem's message in the error colour.
3. Reply failed: the stream's error message in full, in the error colour.
4. Reply in progress, judged by the newest thing the turn has streamed: a status message sent by the server for this turn when one is current; otherwise that the reply is being written when reply text is newest; otherwise "Thinking" when reasoning is newest; otherwise "Running" and the tool's name when a tool call is newest and has no result yet; otherwise "Thinking". Each reply-in-progress state also says that Esc stops the reply.
5. Provider unavailable: that the provider is unavailable and sending is off, in the error colour.
6. Checking the model: "Checking the model".
7. Waking the model: the warm-up message, defaulting to saying the model is waking and the first reply can take a minute.
8. Model not loaded or warm-up failed: the status message from the server, in the warning colour.
9. Ready: "Ready", followed by the model's name when the viewer is signed in and the status names a model.

#### Scenario: A tool is running

- **WHEN** a reply is streaming and its newest tool call, `search_company_news`, has not returned a result
- **THEN** the readout says "Running search_company_news" with an in-progress notch

#### Scenario: Text starts streaming after a tool

- **WHEN** the tool call returns and reply text begins to stream
- **THEN** the readout changes to say the reply is being written

#### Scenario: Server status frame takes precedence

- **WHEN** the server sends a status frame with the message "Waiting for model response" during a turn
- **THEN** the readout shows "Waiting for model response" until reply text or reasoning starts streaming

#### Scenario: Reply failure is shown in full

- **WHEN** a reply ends with the error "Upstream request timed out after 120 seconds."
- **THEN** the readout shows that message in full in the error colour, wrapping onto more lines if needed, and it clears when the next message is sent

#### Scenario: Session expiry outranks everything

- **WHEN** the session has expired while the model is ready
- **THEN** the readout says "Your session has expired." and a Refresh control reloads the page

#### Scenario: Guests do not see the model name

- **WHEN** a visitor who is not signed in opens a public chat whose model is ready
- **THEN** the readout says "Ready" without naming the model

#### Scenario: Signed-in owner sees the model name

- **WHEN** a signed-in user opens a chat whose status reports the model `qwen3:32b` as loaded
- **THEN** the readout says "Ready on qwen3:32b"

### Requirement: Ready is quiet and nothing else is hidden

The ready state SHALL be the readout's least prominent state: muted text with a primary-colour notch, no background and no motion. Warning and error states SHALL wrap instead of truncating. Other states SHALL stay on one line and truncate with an ellipsis when the composer is too narrow.

#### Scenario: Error wraps at phone width

- **WHEN** a reply fails with a long error message at a 360 pixel viewport
- **THEN** the whole message is readable in the toolbar row, wrapped across lines, with the composer's buttons still visible

#### Scenario: In-progress text truncates at phone width

- **WHEN** a tool with a long name is running at a 360 pixel viewport
- **THEN** the readout stays on one line, ending in an ellipsis, and the buttons keep their size

### Requirement: The notch moves only while work is under way

The notch SHALL animate only in the waking, reply-in-progress, recording and transcribing states. In every other state it SHALL be still. When the viewer prefers reduced motion, it SHALL be still in every state.

#### Scenario: Still when ready

- **WHEN** the readout shows the ready state
- **THEN** the notch does not animate

#### Scenario: Reduced motion

- **WHEN** the viewer's system requests reduced motion and a reply is streaming
- **THEN** the notch is shown at full height without animating, and the words still say the reply is in progress

### Requirement: The readout is announced and never relies on colour alone

The readout SHALL be exposed to assistive technology as a polite live status region, and failures (reply failed, provider unavailable, dictation problem) SHALL be announced assertively. Every state SHALL carry its meaning in words, so the notch colour is never the only signal.

#### Scenario: Screen reader hears the change of state

- **WHEN** a reply moves from "Thinking" to running a tool
- **THEN** a screen reader announces the new readout text without moving focus

#### Scenario: Failure is announced

- **WHEN** a reply fails
- **THEN** the error message is announced assertively
