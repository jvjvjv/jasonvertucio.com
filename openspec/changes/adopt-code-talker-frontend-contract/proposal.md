## Why

code-talker 0.10.0 shipped more than the controller refactor the last change absorbed. It also declared the frontend contract public API and made it consumable: `vendor:publish --tag=code-talker-types` ships TypeScript declarations for both chat pages' Inertia props and a discriminated union of every stream event.

This app predates that. It carries a hand-rolled `StreamEvent` in `ChatInterface.tsx` that is barely a type at all:

```ts
interface StreamEvent {
    type: string;                     // no discrimination
    delta?: { text?: string; thinking?: string; reasoning?: string; ... };
    [key: string]: unknown;           // swallows typos silently
}
```

Every field is optional and an index signature accepts anything, so `useChatStream.ts` reads `event.delta?.reasoning` with no compiler guarantee that the branch it just tested implies the field it is about to read. Adopting the package's union turns the wire contract into something the compiler checks.

Tracing that contract also turned up a dead branch and a set of upstream candidates worth recording.

## What Changes

- Publish the package's TypeScript declarations to `resources/js/types/code-talker.d.ts` via `vendor:publish --tag=code-talker-types`.
- Replace `ChatInterface.tsx`'s hand-rolled `StreamEvent` with a real discriminated union: the package's `ChatStreamEvent` widened with the two events this app emits itself.
- Type the two host-only events explicitly rather than leaving them to an index signature:
  - `tool_use_progress` — emitted by `TargetedResumeService.php:281` on each `ToolCallEvent`
  - `page_reload` — emitted at `TargetedResumeService.php:300` when the tool registry latches a reload
- **Remove the dead `thinking_delta` branch** in `useChatStream.ts:236-243`. Nothing can reach it: `laravel/ai`'s Anthropic gateway consumes the raw `thinking_delta` frame itself (`HandlesTextStreaming.php:196-206`) and normalizes it to a `ReasoningDelta`, which `StreamTranslator` maps to `reasoning_block_delta`. It is a leftover from before this app moved onto the package.
- Narrow the hook's event handling against the new union, dropping optional-chaining that the union now proves unnecessary.
- Extend the package's `ChatBotPageProps` for the chat page rather than redeclaring it, keeping the host's `allowed_roles` and `previousHref` additions as a documented delta on top of the package's shape.
- Record the upstream candidates and the turn-runner gap analysis in `design.md` as the basis for a follow-up code-talker change.

Explicitly **not** in this change:

- Adopting the package's `streamChatTurn` client. `api.stream` does three things it does not: retries once on 419 after `refreshCsrfToken()`, fires the `onSessionExpired`/`onActivity` session hooks, and yields *every* frame to the caller. That last one is decisive — `streamChatTurn`'s `dispatch()` ignores unrecognized types in its `default:` case, so it would silently swallow `page_reload` and `tool_use_progress` and break the targeted-resume builder.
- Migrating `TargetedResumeService` onto the package's `ConversationTurnRunner`. The gap analysis says this is worth doing but is blocked on upstreaming the two events first.
- Any change to `../code-talker`. Scoped to this repo; upstream work is noted for a separate change.

## Capabilities

### New Capabilities
- `chat-stream-contract`: how this app types the CodeTalker stream contract — the package's event union as the source of truth, the host-only events layered on top, and the rule that the app does not redeclare shapes the package already publishes.

### Modified Capabilities

None. `chat-component-organization` covers frontend file layout and `host-chat-bot-presentation` covers the server-side payload; neither has a requirement about wire-format typing.

## Impact

- **Code**: `resources/js/types/code-talker.d.ts` (new, published), `resources/js/components/ChatInterface.tsx` (`StreamEvent` replaced), `resources/js/hooks/useChatStream.ts` (dead branch removed, narrowing tightened), `resources/js/chat/pages/ai/ChatBot.tsx` (props extend the package type).
- **Consumers of `StreamEvent`**: `BuilderChatPanel.tsx:215` reads `event.type === "page_reload"` and must keep compiling — it is the reason `page_reload` stays in the union.
- **Behavior**: none intended. This is a typing change plus the removal of an unreachable branch. No prop, event, or endpoint changes.
- **Risk**: published declarations are copies, not a live dependency, so they can drift from the package. Re-publishing is a step in any future code-talker upgrade.
- **Verification**: `npx tsc --noEmit` is the primary gate, since the whole point is compiler-enforced narrowing. The existing PHP suite should stay green as a no-op check.
