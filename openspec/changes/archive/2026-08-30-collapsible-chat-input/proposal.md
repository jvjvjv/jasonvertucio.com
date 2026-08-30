## Why

The chat message composer (`ChatInputArea`) is a plain MUI `TextField` with `multiline minRows={2}` — a fixed two-line box regardless of focus state, that grows as content grows but never shrinks, and resizes abruptly (no transition) as the user types. This is the first, deliberately small step toward a cleaner chat UI closer to Claude.ai's, ahead of two larger, separately-scoped efforts already flagged for later: renaming "Chat Bots" to "Agents" throughout the app, and a broader visual redesign. Both are out of scope here.

## What Changes

- The composer collapses to a single line when it does not have focus, and expands (auto-growing with content, up to a reasonable cap) while focused.
- The resize — both the focus-driven collapse/expand and the per-keystroke growth while typing — animates smoothly instead of snapping instantly.
- No change to send/stop button behavior, keyboard shortcuts, slots (`beforeSend`/`afterSend`), or the message text state itself.

## Capabilities

### New Capabilities
- `chat-input-focus-resize`: The message composer's height responds to focus state and content, animated, independent of the send/submit logic it already has.

### Modified Capabilities
(none — no existing spec covers `ChatInputArea`'s sizing behavior)

## Impact

- **Affected code**: `resources/js/components/ChatInputArea.tsx` only.
- **Not affected**: `ChatInterface.tsx`'s fixed `messagePadding` prop (the message list's static bottom padding, unrelated to this component's own sizing) — already doesn't track the input's height dynamically today when it grows while focused; this change doesn't alter that pre-existing gap, and the common unfocused state (one line) is smaller than today's fixed two-line minimum, not larger.
- **Not affected**: `jvjvjv/code-talker` package — this is host-only presentational code.
