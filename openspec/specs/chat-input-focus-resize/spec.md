# chat-input-focus-resize

## Purpose

The chat message composer's height responds to focus state and content — collapsed to one line when unused, auto-growing up to a cap while focused, animated throughout — independent of the send/submit logic, keyboard handling, and slots the composer already has.

## Requirements

### Requirement: Message composer collapses to one line when unfocused

The chat message composer SHALL render at one line's height whenever it does not have focus, regardless of how much text it currently holds.

#### Scenario: Multi-line draft collapses on blur

- **WHEN** the composer contains several lines of text and the user clicks or tabs away from it
- **THEN** the composer's rendered height becomes one line, and the draft text is preserved (not cleared) though not fully visible

#### Scenario: Empty composer stays at one line

- **WHEN** the composer is empty and unfocused
- **THEN** it renders at one line's height

### Requirement: Message composer expands while focused, up to a cap

While focused, the composer SHALL grow to fit its content as the user types, up to a maximum height, and SHALL NOT shrink back to one line merely because the content became short again while still focused.

#### Scenario: Focused composer grows with typed content

- **WHEN** the user focuses the composer and types enough text to wrap across multiple lines
- **THEN** the composer's rendered height grows to fit the content, up to its configured maximum

#### Scenario: Focused composer does not exceed its cap

- **WHEN** the user types or pastes content exceeding the composer's maximum row cap while focused
- **THEN** the composer's height stops growing at that cap and the content scrolls within it

#### Scenario: Refocusing re-expands a collapsed multi-line draft

- **WHEN** a composer holding a multi-line draft is unfocused (collapsed to one line) and the user refocuses it
- **THEN** the composer expands to fit the full draft, up to its configured maximum

### Requirement: Height changes animate

Every change to the composer's rendered height — collapsing on blur, expanding on focus, and growing while the user types — SHALL animate smoothly rather than changing instantaneously.

#### Scenario: Blur-triggered collapse is animated

- **WHEN** a focused, multi-line composer loses focus
- **THEN** its height transitions smoothly to one line rather than snapping immediately

#### Scenario: Typing-triggered growth is animated

- **WHEN** a focused composer's content wraps to an additional line as the user types
- **THEN** its height transitions smoothly to the new size rather than snapping immediately
