## Purpose

How the chat message composer is laid out: its controls never cover the text being typed, and the composer never covers the conversation above it, on every chat surface in the admin and the public chat.

## ADDED Requirements

### Requirement: Composer controls never overlap the message text

The composer SHALL place its controls (Microphone, Send, Stop, Refresh) and its status readout in a toolbar row below the text area, inside the same bordered surface. No control or status text SHALL be drawn over the text area at any draft length, scroll position or focus state.

#### Scenario: Long draft scrolls clear of the buttons

- **WHEN** a focused composer holds more text than its height cap and the user scrolls the text to its last line
- **THEN** every line of text is fully visible inside the text area and none of it is behind Microphone, Send or Stop

#### Scenario: Caret at the end of a long line

- **WHEN** the user types a single line long enough to wrap, with the caret at the end of the last line
- **THEN** the text wraps at the text area's right edge and the caret is not behind any control

#### Scenario: Dictation state does not overlay the text

- **WHEN** a voice user is recording and the composer already holds text
- **THEN** the recording time is shown in the toolbar row and none of the draft is covered by it

### Requirement: Composer takes its own space below the transcript

The chat card SHALL lay out the transcript and the composer as a column within a fixed overall height, with the composer in normal flow beneath the transcript. As the composer grows, the transcript SHALL become shorter; the composer SHALL NOT be drawn over any part of the transcript.

#### Scenario: Growing draft shortens the transcript

- **WHEN** the user types a draft that grows the focused composer from one line to its cap
- **THEN** the transcript's visible area shrinks by the same amount and no message is covered by the composer

#### Scenario: Collapsing the composer gives the space back

- **WHEN** a multi-line composer loses focus and collapses to one line
- **THEN** the transcript's visible area grows back by the height the composer gave up

#### Scenario: Slots above and below the composer stay in the column

- **WHEN** a chat surface passes `beforeSend` and `afterSend` content (as the public chat does for the visitor form and the persona note)
- **THEN** that content is shown above and below the composer surface, inside the card's column, and does not cover the transcript

### Requirement: Transcript stays pinned to its newest message while the composer resizes

When the transcript is scrolled to its newest message, it SHALL remain scrolled to its newest message as the composer grows or shrinks. When the user has scrolled up to read earlier messages, resizing the composer SHALL NOT move their reading position to the end.

#### Scenario: At the bottom while typing

- **WHEN** the transcript shows its newest message and the user types until the composer grows by several lines
- **THEN** the newest message is still fully visible above the composer

#### Scenario: Reading earlier messages while typing

- **WHEN** the user has scrolled the transcript up to an earlier message and then types until the composer grows
- **THEN** the earlier message they were reading stays in view and the transcript does not jump to the end

### Requirement: Composer growth cap is smaller on narrow screens

The focused composer SHALL grow to at most eight lines on screens at or above the small breakpoint, and to at most five lines below it, scrolling its content beyond the cap.

#### Scenario: Phone-width cap

- **WHEN** the viewport is 360 pixels wide and the user pastes twenty lines into the focused composer
- **THEN** the composer stops growing at five lines and the content scrolls inside it

#### Scenario: Desktop cap

- **WHEN** the viewport is 1280 pixels wide and the user pastes twenty lines into the focused composer
- **THEN** the composer stops growing at eight lines and the content scrolls inside it

### Requirement: Stop takes Send's place while a reply streams

While a reply is streaming, the composer SHALL show a Stop control in the position Send occupies, and activating it or pressing Esc SHALL stop the reply. When the reply ends, Send SHALL return in the same position.

#### Scenario: Stopping a reply

- **WHEN** a reply is streaming and the user activates Stop
- **THEN** the reply stops and Send is shown again where Stop was

#### Scenario: Esc stops a reply

- **WHEN** a reply is streaming, the composer has focus, and no dictation recording is in progress, and the user presses Esc
- **THEN** the reply stops
