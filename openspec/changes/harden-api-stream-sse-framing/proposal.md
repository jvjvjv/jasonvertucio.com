## Why

Adopting the code-talker frontend contract meant reading the package's published `streamChatTurn` client closely. That client was rejected as a replacement for `api.stream` — it has no 419/CSRF retry, no session hooks, and its `dispatch()` silently drops unrecognized event types, which would break the targeted-resume builder. That reasoning is recorded in the archived `adopt-code-talker-frontend-contract` change.

But its **SSE framing is genuinely better than ours**, and `resources/js/api.ts` is entirely our own file — nothing upstream publishes over it. The improvements can be taken without taking the client.

Four differences, in descending order of how likely they are to bite:

| Behavior | `api.stream` today | Package client |
| --- | --- | --- |
| Frame boundary | splits on `\n` (single newline) | splits on `\n\n` (true SSE frame) |
| `data:` prefix | requires the space in `"data: "` | `startsWith('data:')`, then `.slice(5).trim()` |
| Multi-line `data:` | yields each line as a separate frame | joins them into one payload |
| Stream tail | never flushes the decoder after the read loop | `decoder.decode()` then dispatches the remainder |

None of these misbehave against the package's current output, which always writes single-line `data: {json}\n\n`. That is why this is a robustness change rather than a bug fix — it removes assumptions about the *emitter* that the SSE format itself does not guarantee.

The tail flush is the one with a real failure mode: an `error` frame is terminal and is **not** followed by `[DONE]`, so a stream that closes on an error without a trailing newline would have its last frame dropped.

## What Changes

- Parse frames on blank-line boundaries rather than single newlines, carrying an incomplete trailing frame forward across reads.
- Accept `data:` with or without a following space.
- Join multiple `data:` lines within one frame into a single payload before yielding.
- Flush the `TextDecoder` after the read loop ends and dispatch any remaining buffered frame.
- Keep everything that makes `api.stream` ours: the 419 retry after `refreshCsrfToken()`, the `onSessionExpired`/`onActivity` hooks, and yielding **every** frame to the caller rather than routing to fixed callbacks.

Explicitly not in scope: adopting `streamChatTurn`, and any change to `useChatStream` or the event union.

## Capabilities

### New Capabilities
- `sse-transport-framing`: how the app's stream transport turns bytes into frames — boundary detection, prefix tolerance, multi-line payloads, and end-of-stream flushing — independent of what the frames mean.

### Modified Capabilities

`chat-stream-contract` may need a delta if the requirement that the app keeps its own transport should now also state the framing guarantees. Check when picking this up.

## Impact

- **Code**: `resources/js/api.ts`, the `stream()` generator only. `get`/`post` and the session plumbing are untouched.
- **Consumers**: `useChatStream` is the only caller and needs no change — it already treats each yielded string as one JSON payload and skips `[DONE]`.
- **Risk**: low but not zero — this is the transport under every chat turn, and the app has no frontend test runner. Worth exercising both a normal turn and an error-terminated turn by hand, since the tail flush only matters on the latter.
- **Not urgent**: nothing is known to be broken today. This is hardening against emitter changes, so it can wait for a quiet moment.
