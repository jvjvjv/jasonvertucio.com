## Purpose

Defines how AI usage is shown in the admin and on the public chat page: usage is measured in tokens, and no dollar cost of AI usage is shown or sent to the browser.

## ADDED Requirements

### Requirement: Admin usage is shown as a token count

Wherever the admin shows AI usage — the Applications list "Tokens" column (formerly "AI Usage"), the application details "Chat Usage" section, the application status bar, the AI Personas list "Usage" column, and the Conversations list and detail pages — the system SHALL show the total token count, abbreviated for large values (for example `12.4K`), with no unit suffix. Hovering or focusing it SHALL reveal the input and output token counts. It SHALL NOT show a dollar amount, and its appearance SHALL NOT vary with how much the usage cost.

When usage has no token counts recorded, the system SHALL show an em dash.

#### Scenario: A conversation with recorded usage

- **WHEN** an administrator views a conversation whose usage is 10,000 input tokens and 2,400 output tokens
- **THEN** the usage shows `12.4K`
- **AND** its tooltip lists 10,000 input tokens and 2,400 output tokens
- **AND** no dollar amount appears

#### Scenario: A persona's usage is summed from its conversations

- **WHEN** an administrator opens the AI Personas list and a persona has two conversations of 1,000 and 3,000 total tokens
- **THEN** that persona's Usage column shows `4K`

#### Scenario: A conversation with no usage recorded

- **WHEN** an administrator views a conversation whose usage has not been synced
- **THEN** the usage shows an em dash

#### Scenario: Expensive and cheap usage look the same

- **WHEN** two conversations have the same token counts but different recorded costs
- **THEN** their usage displays are identical

### Requirement: The public chat page shows no AI cost

The chat page's history panel SHALL NOT show a persona cost total or a cost for any saved chat. It SHALL still list the visitor's saved chats with their labels, last-updated times and stale markers.

#### Scenario: A visitor opens the history panel

- **WHEN** a visitor opens a persona's chat page and views the history panel
- **THEN** no "Overall Persona Cost" row and no per-chat "Cost:" line appear
- **AND** the visitor's saved chats are still listed

### Requirement: No dollar cost of AI usage reaches the browser

Page props sent to the browser SHALL NOT include a dollar cost of AI usage. Usage objects on the Applications list, the application page, the AI Personas list and the Conversations list and detail pages SHALL carry `input_tokens`, `output_tokens`, `total_tokens` and `synced_at`, and SHALL NOT carry `cost_usd`. The chat page's `bot` prop SHALL NOT carry `total_cost_usd`.

The system SHALL keep recording the cost of each conversation's usage; this requirement governs only what is sent to the browser.

#### Scenario: The application page's usage omits cost

- **WHEN** an administrator opens an application that has a session with synced usage
- **THEN** the session's `usage` prop has `input_tokens`, `output_tokens`, `total_tokens` and `synced_at`
- **AND** it has no `cost_usd` key

#### Scenario: The conversation pages' usage omits cost

- **WHEN** an administrator opens the Conversations list, or a conversation's detail page, for a conversation with synced usage
- **THEN** each `usage` prop has no `cost_usd` key

#### Scenario: The personas list's usage omits cost

- **WHEN** an administrator opens the AI Personas list and a persona has conversations with synced usage
- **THEN** that persona's `usage` prop carries its summed input and output tokens and has no `cost_usd` key

#### Scenario: A persona with no synced usage

- **WHEN** an administrator opens the AI Personas list and a persona has no conversations with synced usage
- **THEN** that persona's `usage` prop is `null`

#### Scenario: The chat page omits the persona's total cost

- **WHEN** a visitor opens a persona's chat page
- **THEN** the `bot` prop has no `total_cost_usd` key

#### Scenario: Cost is still recorded

- **WHEN** a conversation's usage is synced
- **THEN** its stored cost is updated as before
