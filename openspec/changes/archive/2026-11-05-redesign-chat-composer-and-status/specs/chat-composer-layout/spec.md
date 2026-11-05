## Purpose

How the chat message composer is laid out: its controls never cover the text being typed, and the composer never covers the conversation above it, on every chat surface in the admin and the public chat.

## ADDED Requirements

### Requirement: Composer controls never overlap the message text

The composer SHALL be one bordered surface holding a text column and, to its right behind a hairline, an action margin with its controls (Microphone, Send, Stop, Refresh). The text SHALL wrap at the text column's edge, and the margin's controls SHALL stay at the bottom of the row as the text grows. The status rail SHALL run along the surface's top edge and the status words SHALL be shown in a caption line beneath the surface. No control or status text SHALL be drawn over the text column at any draft length, scroll position or focus state.

#### Scenario: Long draft scrolls clear of the buttons

- **WHEN** a focused composer holds more text than its height cap and the user scrolls the text to its last line
- **THEN** every line of text is fully visible inside the text column and none of it is behind Microphone, Send or Stop

#### Scenario: Caret at the end of a long line

- **WHEN** the user types a single line long enough to wrap, with the caret at the end of the last line
- **THEN** the text wraps at the text column's edge, before the action margin, and the caret is not behind any control

#### Scenario: Buttons stay at the bottom of a tall draft

- **WHEN** the focused composer has grown to several lines
- **THEN** Microphone and Send are aligned to the bottom of the action margin

#### Scenario: Collapsed composer is one line

- **WHEN** the composer is not focused
- **THEN** it is one line of text tall, with the rail above and the action margin beside it

#### Scenario: Dictation state does not overlay the text

- **WHEN** a voice user is recording and the composer already holds text
- **THEN** the recording time is shown in the caption line beneath the composer and none of the draft is covered by it

### Requirement: Composer takes its own space below the transcript

The chat card SHALL lay out the transcript and the composer as a column whose height fills the viewport below the page's own chrome, with the composer in normal flow beneath the transcript. As the composer grows, the transcript SHALL become shorter; the composer SHALL NOT be drawn over any part of the transcript.

#### Scenario: Card reaches the bottom of the window

- **WHEN** a chat page loads with any amount of page chrome above the chat card
- **THEN** the card's bottom edge meets the bottom of the window and the page itself does not scroll

#### Scenario: Growing draft shortens the transcript

- **WHEN** the user types a draft that grows the focused composer from one line to its cap
- **THEN** the transcript's visible area shrinks by the same amount and no message is covered by the composer

#### Scenario: Collapsing the composer gives the space back

- **WHEN** a multi-line composer loses focus and collapses to one line
- **THEN** the transcript's visible area grows back by the height the composer gave up

#### Scenario: Slots above and below the composer stay in the column

- **WHEN** a chat surface passes `beforeSend` and `afterSend` content (as the public chat does for the visitor form and the persona note)
- **THEN** the `beforeSend` content is shown above the composer surface and the `afterSend` content below its caption line, inside the card's column, and neither covers the transcript

### Requirement: Transcript stays pinned to its newest message while the composer resizes

When the transcript is scrolled to its newest message, it SHALL remain scrolled to its newest message as the composer grows or shrinks, including when the caption line wraps. When the user has scrolled up to read earlier messages, resizing the composer SHALL NOT move their reading position to the end.

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

While a reply is streaming, the composer SHALL show a Stop control in the position Send occupies in the action margin, and activating it or pressing Esc SHALL stop the reply. When the reply ends, Send SHALL return in the same position.

#### Scenario: Stopping a reply

- **WHEN** a reply is streaming and the user activates Stop
- **THEN** the reply stops and Send is shown again where Stop was

#### Scenario: Esc stops a reply

- **WHEN** a reply is streaming, the composer has focus, and no dictation recording is in progress, and the user presses Esc
- **THEN** the reply stops
