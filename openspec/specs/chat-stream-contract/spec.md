# chat-stream-contract

## Purpose

Defines how this app types and consumes the CodeTalker chat streaming contract: consuming the package's published declarations as source of truth, keeping stream events as a closed discriminated union, declaring the app's own host-emitted events explicitly, keeping the stream hook free of unreachable event handling, and retaining the app's own stream transport rather than the package's client.

## Requirements

### Requirement: The package's published declarations are the source of truth for the stream contract

The app SHALL consume the stream event and chat page prop shapes from the package's published declarations at `resources/js/types/code-talker.d.ts`, and SHALL NOT redeclare any shape the package already publishes. Where the app needs more than the package provides, it SHALL extend the published type rather than restate it.

#### Scenario: Published declarations are present

- **WHEN** the app's TypeScript is compiled
- **THEN** `resources/js/types/code-talker.d.ts` exists and exports `ChatStreamEvent`, `ChatBotPageProps`, and `ChatMessage`

#### Scenario: Chat page props extend rather than restate

- **WHEN** the chat page declares the props it receives
- **THEN** it extends the package's `ChatBotPageProps` and adds only the host's own additions, rather than redeclaring `messageUrl`, `resetUrl`, `history`, and the rest

#### Scenario: Upgrading the package refreshes the declarations

- **WHEN** the code-talker dependency is upgraded
- **THEN** `vendor:publish --tag=code-talker-types --force` is re-run so the declarations match the installed package

### Requirement: Stream events are a discriminated union, not an open bag

The app's `StreamEvent` type SHALL be a union discriminated on `type`, formed from the package's `ChatStreamEvent` plus the events this app emits itself. It SHALL NOT carry an index signature, so an unrecognized property is a compile error rather than silently accepted.

#### Scenario: Testing the discriminant narrows the payload

- **WHEN** code tests `event.type === 'content_block_delta'`
- **THEN** `event.delta.text` is reachable without optional chaining and is typed as `string`

#### Scenario: A misspelled property fails the build

- **WHEN** code reads a property that no member of the union declares, such as `event.mesage`
- **THEN** `npx tsc --noEmit` reports an error instead of inferring `unknown`

#### Scenario: An unhandled event type is inert, not a crash

- **WHEN** the server emits an event type the union does not include
- **THEN** the hook ignores it without throwing, preserving forward compatibility with a newer package

### Requirement: Host-emitted events are declared explicitly

The two events this app emits beyond the package contract SHALL be declared as named types in the union, each documented with the server code that emits it, so they are distinguishable from package events at a glance.

#### Scenario: Tool-use progress is typed

- **WHEN** `TargetedResumeService` emits `tool_use_progress` with `text` and `tools`
- **THEN** the union contains a member with `type: 'tool_use_progress'`, `text: string`, and `tools: string[]`

#### Scenario: Page reload is typed and still consumed

- **WHEN** `TargetedResumeService` emits `page_reload`
- **THEN** the union contains a member with `type: 'page_reload'` and `BuilderChatPanel` continues to compile against it

### Requirement: The stream hook contains no unreachable event handling

`useChatStream` SHALL NOT contain branches for event shapes that no server in this system emits. Specifically it SHALL NOT handle `content_block_delta` deltas carrying `thinking` or a `type` of `thinking_delta`, because `laravel/ai` normalizes Anthropic's thinking deltas into `ReasoningDelta` before the package translates them to `reasoning_block_delta`.

#### Scenario: Anthropic reasoning still renders

- **WHEN** a turn runs on the Anthropic-backed system used for cover letters and targeted resumes and the model produces extended thinking
- **THEN** the reasoning renders in the UI, arriving as `reasoning_block_delta`

#### Scenario: No thinking_delta branch remains

- **WHEN** `resources/js/hooks/useChatStream.ts` is searched for `thinking_delta` or `delta.thinking`
- **THEN** there are no matches

### Requirement: The app keeps its own stream transport

The chat stream SHALL continue to be read through `api.stream`, which retries once on HTTP 419 after refreshing the CSRF token, fires the session-expiry and activity hooks, and yields every frame to its caller. The app SHALL NOT replace this with the package's published `streamChatTurn` client while that client drops unrecognized event types.

#### Scenario: A session that expires mid-send recovers

- **WHEN** a message POST returns 419
- **THEN** the CSRF token is refreshed, the send is retried once, and the session-expiry handler fires

#### Scenario: Host events reach the hook

- **WHEN** the server emits `page_reload` or `tool_use_progress`
- **THEN** the frame is delivered to `useChatStream` and passed to `onEvent`, rather than being filtered out by the transport
