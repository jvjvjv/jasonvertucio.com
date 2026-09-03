## Context

This app moved onto `jvjvjv/code-talker` incrementally. The chat UI was written first, against a hard-coded backend, and its types were shaped by what that backend happened to send. 0.10.0 is the first release to declare the frontend contract as public API and publish it, so this is the first opportunity to replace guesses with the real thing.

Two artifacts are now publishable:

| Tag | Publishes to | Nature |
| --- | --- | --- |
| `code-talker-types` | `resources/js/types/code-talker.d.ts` | declarations — should track the package |
| `code-talker-client` | `resources/js/code-talker-stream.ts` (+ the above) | a starting point the host then owns |

Only the first is adopted here. The reasoning for both is below.

What the app has today:

- `ChatInterface.tsx` declares `StreamEvent` with every field optional and `[key: string]: unknown`. It is not a union, so `event.type === 'x'` narrows nothing.
- `useChatStream.ts` compensates with optional chaining everywhere — `event.delta?.reasoning`, `event.text ?? ''`, `event.tools ?? []`.
- The app emits two events the package does not: `tool_use_progress` and `page_reload`, both from `TargetedResumeService`.

## Goals / Non-Goals

**Goals:**

- Make the wire contract compiler-checked, so a server-side event change surfaces as a type error rather than a runtime `undefined`.
- Stop maintaining local copies of shapes the package publishes.
- Delete the dead `thinking_delta` branch, with the evidence recorded so it does not get re-added.
- Produce a concrete, evidence-backed list of what belongs upstream, for a follow-up code-talker change.

**Non-Goals:**

- Any behavior change. This is typing plus one unreachable branch removed.
- Replacing `api.stream` or the hook's streaming logic (RAF batching, block coalescing, persist-on-error, benign-abort detection).
- Editing `../code-talker`. Upstream candidates are documented, not implemented.
- Migrating `TargetedResumeService` onto the package turn runner.

## Decisions

### Adopt the types, not the client

The published client is genuinely better at SSE framing than `api.stream`: it splits on blank lines (true frame boundaries) rather than single newlines, tolerates `data:` without a space, joins multi-line `data:` fields, and flushes the decoder's tail after the loop. `api.stream` does none of that.

It is still the wrong swap, for three reasons in ascending order of severity:

1. `api.stream` retries once on HTTP 419 after `refreshCsrfToken()`. `streamChatTurn` has no retry.
2. `api.stream` fires `onSessionExpired` and `onActivity`. The client has no equivalent hooks.
3. `streamChatTurn` interprets frames itself and routes them to fixed callbacks (`onText`, `onReasoning`, …), with a `default:` case that **silently ignores unrecognized types**. `page_reload` and `tool_use_progress` are unrecognized. `BuilderChatPanel.tsx:215` acts on `page_reload`; adopting the client as-is would break the targeted-resume builder with no error anywhere.

The framing improvements are worth having eventually, but they belong as a fix inside `api.stream` — which keeps yielding raw frames — not as a wholesale transport swap. Recorded as a follow-up rather than smuggled into a typing change.

*Alternative considered:* adopt the client and extend its `dispatch()` with the host events. Rejected — that forks a published file the package explicitly says it will not publish over, so the app would own the divergence forever, and it still leaves the 419/session gap to re-solve.

### The union is `ChatStreamEvent` plus host events, with no index signature

```ts
import type { ChatStreamEvent } from '@/types/code-talker';

/** Emitted by TargetedResumeService on each ToolCallEvent. */
export interface ToolUseProgressEvent {
    type: 'tool_use_progress';
    text: string;
    tools: string[];
}

/** Emitted by TargetedResumeService when the tool registry latches a reload. */
export interface PageReloadEvent {
    type: 'page_reload';
}

export type StreamEvent = ChatStreamEvent | ToolUseProgressEvent | PageReloadEvent;
```

Dropping the index signature is the point of the exercise: it is what turns a misspelled property from `unknown` into a build error. The hook's `default`/else-fall-through already ignores unhandled types, so a newer package emitting something new stays inert at runtime.

### `thinking_delta` is provably dead

Worth recording the chain, because "Anthropic sends thinking deltas" is true and still does not make the branch reachable:

1. Anthropic's wire format does emit `thinking_delta`.
2. `laravel/ai`'s Anthropic gateway consumes it directly — `HandlesTextStreaming.php:196` tests `$deltaType === 'thinking_delta'`, reads `$data['delta']['thinking']`, and yields a `ReasoningDelta` at line 206.
3. The package's `StreamTranslator::translate()` maps `ReasoningDelta` to `reasoning_block_delta` with `delta.reasoning`.
4. Its only `content_block_delta` mapping is from `TextDelta`, producing `delta: { text }` — never `thinking`.

So the Anthropic-backed system reaches the browser as `reasoning_block_delta`, which the hook already handles. The `thinking_delta` branch dates from before the package, when this app parsed Anthropic's stream itself. The new union makes it a type error anyway, since `ContentBlockDeltaEvent['delta']` has no `thinking`.

### Chat page props extend the package type

The host adds `allowed_roles` and `previousHref` (both from the change archived as `host-chat-bot-presentation`). Extending keeps that delta visible as exactly two lines instead of burying it in a restated interface:

```ts
interface ChatBotProps extends ChatBotPageProps {
    bot: ChatBotSummary & { allowed_roles: string[] };
    previousHref?: string | null;
}
```

### Published declarations are copies, and copies drift

`vendor:publish` writes a file; it does not link one. Nothing detects the package changing its contract afterwards. Mitigated procedurally — re-publishing with `--force` becomes part of upgrading code-talker, and it is cheap to diff. Not worth building tooling for at this scale.

## Upstream candidates (for a follow-up code-talker change)

Recorded here so the analysis is not lost. **None of this is implemented by this change.**

### 1. `page_reload` — the package is already half-way there

`ToolResultConverter`'s docblock names `_page_reload` as a side-channel it deliberately preserves when returning structured tool payloads. The package therefore already understands the convention — it just never emits the browser event. The latching logic lives in this app (`TargetedResumeToolRegistry::consumePageReload()`), but there is nothing app-specific about "a tool changed server state, tell the browser to refresh."

### 2. `tool_use_progress` — generic by construction

The emitter is a plain `ToolCallEvent` → frame mapping with no app knowledge in it. The package owns tool calling (MCP tools, `search-web`), and `ConversationTurnRunner` already collects `ToolCallEvent`s into `$toolCalls` at the exact point the frame would be emitted — it simply does not yield one.

### 3. `TargetedResumeService`'s turn loop — the gap analysis

Its own comment calls it *"Mirrors the pre-0.6.0 loop"*, and it has since fallen behind. Comparing it against `ConversationTurnRunner::run()`:

**In the package runner, missing from the host loop:**

| Capability | Consequence of not having it |
| --- | --- |
| `TurnGuards::clientAborted()` | a cancelled turn keeps generating and billing tokens |
| Per-step max-stream-duration guard | a runaway reasoning model can hang the request indefinitely |
| Non-recoverable `ErrorEvent` → fail the turn | an LM Studio context overflow finishes as a silent success |
| `RawExchangeContext` frame recording | `ai:read-exchange` cannot inspect these turns |
| `TurnSequence::labelFor()` | the host inlines `"{$base}.{$attempt}"` by hand |
| Per-event `Log::debug` tracing | no stream-level diagnostics |

In short, the targeted-resume path never received the 0.9.0 reliability work — max-duration budgeting, partial-content persistence, error reason codes — that the chat-bot path did.

**In the host loop, missing from the package runner:** the two event emissions above, and bespoke `AiLlmMessage` persistence carrying `turn_number` per attempt (which the runner also does, via `TurnSequence`).

**Conclusion:** adopting `ConversationTurnRunner` would be a net win, but it is **blocked** — the runner has no way to emit the two host events, so migrating today would break the builder panel. Sequence it as: upstream the events first, then migrate the loop. That ordering is why this change stays host-only.

## Risks / Trade-offs

- **Tightening the union may surface pre-existing type errors in files that leaned on the index signature.** → That is the change working as intended; each one is a real unchecked assumption. If the blast radius turns out large, the fallback is to keep the union and add explicit narrow casts at the few call sites, rather than restoring the index signature.
- **Published declarations drift from the package silently.** → Re-publish on upgrade; the file is small enough to diff by eye.
- **Removing the `thinking_delta` branch is irreversible from the UI's perspective if some provider does send it.** → The evidence chain above is specific and checkable, and the branch is unreachable through `laravel/ai` regardless of provider, since normalization happens in the gateway. If a future provider bypassed that, `reasoning_block_delta` is the correct target anyway.
- **This change touches no tests, because the app has no frontend test runner.** → `npx tsc --noEmit` is the gate. Worth noting the absence as its own finding rather than pretending coverage exists.
- **`npx tsc --noEmit` does not currently pass, so "typecheck is clean" cannot be the gate.** → Baseline recorded below; the gate is *no new errors, and zero errors in the touched files*.

## Typecheck baseline

Measured before any edit. `npx tsc --noEmit` exits non-zero with **8 errors across 4 files**:

| File | Errors |
| --- | --- |
| `resources/js/admin/pages/ai/bots/Index.tsx` | 4 |
| `resources/js/admin/pages/ai/memories/Edit.tsx` | 2 |
| `resources/js/admin/pages/ai/system-prompts/Index.tsx` | 1 |
| `resources/js/chat/pages/ai/ChatBotsIndex.tsx` | 1 |

All eight predate this change and are unrelated to the stream contract. Crucially, **none of them are in the three files this change edits** — `ChatInterface.tsx`, `useChatStream.ts`, and `ChatBot.tsx` are currently clean — so tightening the union has a clean surface to land on.

The acceptance gate is therefore: the count stays at 8, the same four files carry them, and the touched files report zero. Fixing the pre-existing eight is explicitly out of scope; two of them (`row.allowed_roles` possibly undefined) are adjacent to this work and worth their own change.

## Open Questions

None blocking. The upstream sequencing question is answered (events before loop), and the client-adoption question is answered with evidence.
