## Context

`resources/js/api.ts`'s `stream()` generator is the transport under every chat turn (`useChatStream` → `ChatBotController::message()` / `TargetedResumeController`'s equivalent). It reads the response body, decodes bytes, and yields JSON payload strings to its caller. Today it does that with a naive line-splitting parser (`buffer.split("\n")`, a hard-coded `"data: "` prefix, no end-of-stream flush) written before the code-talker package's own SSE client existed.

`adopt-code-talker-frontend-contract` (archived) evaluated replacing `api.stream` with the package's published `streamChatTurn` and rejected it — no 419/CSRF retry, no session hooks, and its `dispatch()` silently drops event types it doesn't recognize, which would break `BuilderChatPanel`'s `page_reload` and `tool_use_progress` handling. But that client's frame-parsing logic (`consume()` in `vendor/jvjvjv/code-talker/resources/js/code-talker-stream.ts`) is strictly more correct than ours, and `api.ts` is entirely app-owned — nothing upstream publishes over it. This change ports that parsing logic into `api.stream`, leaving everything that makes it ours untouched.

## Goals / Non-Goals

**Goals:**
- Parse SSE frames on the true `\n\n` boundary, carrying an incomplete trailing frame across reads.
- Accept `data:` with or without a following space.
- Join multiple `data:` lines within one frame into a single payload.
- Flush the `TextDecoder` after the read loop ends and dispatch whatever frame is left in the buffer, so a stream that ends on a terminal `error` frame (which is never followed by `[DONE]`) doesn't lose its last frame.
- Preserve `api.stream`'s existing behavior exactly: the 419-retry-after-CSRF-refresh path, `onActivity`/`onSessionExpired` hooks, `onResponse` callback timing, and yielding every frame to the caller (no fixed-callback dispatch, no filtering by event type).

**Non-Goals:**
- Adopting `streamChatTurn` or any part of its callback-based API.
- Changing `useChatStream`, the `StreamEvent`/`ChatStreamEvent` union, or any consumer's event handling.
- Changing the server-side SSE encoder (`SseFrameEncoder`) — it already emits well-formed `data: {json}\n\n` frames; this change only makes the client stop assuming that shape.
- Adding a frontend test runner. The repo has none today (see `verification-before-completion` guidance — testing is by hand here, same as the rest of the frontend).

## Decisions

### Port the package client's parsing logic, not the client itself
The four framing differences in the proposal are exactly what `code-talker-stream.ts`'s `consume()`/`dispatch()` already solve correctly. Rather than re-deriving that logic, `stream()`'s inner loop is rewritten to match it structurally: split the accumulated buffer on `\n\n`, hold back the last (possibly partial) element, and for each complete frame join all `data:` lines (tolerating a missing space) into one payload before yielding.

Alternative considered: only fix the boundary (`\n\n`) and leave line-splitting for `data:` extraction. Rejected — a multi-line `data:` frame would still yield each line as a separate (and individually unparseable) payload, defeating half the point.

### Yield the joined payload string, not a parsed event
`stream()`'s contract is `AsyncGenerator<string>` — it yields raw JSON text and lets `useChatStream` do `JSON.parse`. This change keeps that contract exactly; it only changes how the string is assembled from the frame. `useChatStream` needs no changes, matching the proposal's explicit non-goal.

### Flush after the loop, not per-chunk
`decoder.decode(chunk.value, { stream: true })` is kept unchanged inside the loop (it must stay `stream: true` so multi-byte UTF-8 sequences split across chunks decode correctly). After the loop exits (`chunk.done`), call `decoder.decode()` with no arguments once to flush any buffered partial multi-byte sequence, append it to `buffer`, and if what's left is non-blank, run it through the same frame-dispatch path as a final frame. This mirrors `consume()`'s post-loop flush exactly.

### Treat an empty joined payload as "skip", not "yield empty string"
Today's code already does `if (!data.trim()) continue`. The rewritten loop keeps this: a frame with no `data:` lines (e.g. a bare `\n\n` keep-alive, if one is ever sent) joins to `""` and is skipped rather than yielded, so `useChatStream`'s existing `if (!jsonStr || jsonStr === "[DONE]") continue` guard keeps working unchanged.

## Risks / Trade-offs

- **No frontend test runner** → Mitigated by hand-testing both a normal turn (visible in existing manual QA flow) and an error-terminated turn (trigger `max_stream_duration` or a forced backend error) after the change, per the proposal's own note. This is the one path where the tail-flush behavior actually differs from today.
- **Behavior change is silent under current server output** → The server always emits single-line `data: {json}\n\n`, so there is no case today where old and new parsing disagree. Risk is limited to *future* emitter changes this change is explicitly hardening against, not a regression risk now.
- **Divergence from the package client over time** → The package's `consume()`/`dispatch()` could change in a later code-talker release without this hand-ported copy following along. Accepted: the proposal already rejected taking a live dependency on the package's client for unrelated reasons (missing 419/session/forward-compat handling), so a hand-maintained copy of just the framing logic is the deliberate trade-off, not an oversight.

## Migration Plan

Single-file change, no data migration, no feature flag. Land `resources/js/api.ts`'s `stream()` rewrite, rebuild frontend assets (`npm run build` / `npm run dev`), and hand-verify both turn types. Rollback is a plain revert — the change touches no persisted state or server contract.

## Open Questions

- Whether `chat-stream-contract`'s "The app keeps its own stream transport" requirement should gain a scenario describing the framing guarantees, or whether that's fully owned by the new `sse-transport-framing` capability with just a cross-reference. Resolved in this pass: add one scenario to the existing requirement pointing at the new capability, rather than duplicating the framing details in two specs.
