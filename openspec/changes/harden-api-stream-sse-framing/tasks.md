## 1. Rewrite `stream()`'s frame parsing

- [ ] 1.1 Replace the `buffer.split("\n")` loop in `resources/js/api.ts`'s `stream()` with a `buffer.split("\n\n")` loop, holding back the last (possibly partial) element as the new `buffer`.
- [ ] 1.2 Within each complete frame, extract every line starting with `data:` (not just `"data: "`), strip the `data:` prefix and trim, and join the results into one payload string before yielding.
- [ ] 1.3 Keep the existing `if (!data.trim()) continue` behavior for a frame that joins to an empty payload (e.g. no `data:` lines present).
- [ ] 1.4 Leave `decoder.decode(chunk.value, { stream: true })` inside the loop unchanged.

## 2. Add the end-of-stream flush

- [ ] 2.1 After the `for` read loop exits, call `decoder.decode()` with no arguments to flush any buffered partial multi-byte sequence, and append the result to `buffer`.
- [ ] 2.2 If the resulting `buffer` is non-blank, run it through the same `data:`-extraction/join logic as a regular frame and yield the payload if non-empty.

## 3. Verify no regressions in what's out of scope

- [ ] 3.1 Confirm the 419-retry-after-`refreshCsrfToken()` path, `onActivity`/`onSessionExpired` hooks, and `onResponse` callback timing are untouched.
- [ ] 3.2 Confirm `stream()`'s signature and its `AsyncGenerator<string>` contract (yielding raw JSON text, not parsed events) are unchanged, and that `useChatStream` needs no edits.

## 4. Sync specs

- [ ] 4.1 Run `/opsx:sync harden-api-stream-sse-framing` (or equivalent) to merge the `sse-transport-framing` and `chat-stream-contract` delta specs into `openspec/specs/`.

## 5. Manual verification

- [ ] 5.1 Run a normal chat turn end-to-end (e.g. `/chats/<slug>`) and confirm text/reasoning/tool-use render exactly as before.
- [ ] 5.2 Trigger an error-terminated turn (e.g. force `max_stream_duration` or a backend exception mid-turn) and confirm the error frame is still received and surfaced, verifying the end-of-stream flush actually matters on this path.
- [ ] 5.3 Run `npx tsc --noEmit` and the project's lint command against `resources/js/api.ts`.
