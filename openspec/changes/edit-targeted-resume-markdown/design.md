## Context

Targeted resumes are built by a chat-driven agent (`TargetedResumeService::continueConversation`, `resources/js/admin/pages/resume/targeted/BuilderChatPanel.tsx`). When the admin is happy with the agent's output they hit "Finalize", which POSTs the raw markdown to `TargetedResumeController::finalize`, which calls `TargetedResumeService::saveTailoredResume()` and stores it at `tailored_data.markdown` (also read as `tailored_data.content` by `TargetedResumeDocumentService::buildTemplateData`). DOCX/PDF are then generated from that markdown via `MarkdownToOpenXmlConverter`. A separate `regenerate` action re-runs DOCX/PDF generation from whatever is currently stored.

There is currently no way to edit `tailored_data.markdown` except by re-prompting the chat agent until it reproduces the desired text. `AiConversation` already tracks a linear message history (`AiConversationMessage`, with an existing unused JSON `metadata` column) that both the in-app chat agent and this repo's MCP tools (`GetTargetedResumeContextTool`) read from, so it's a natural place to record "this changed outside the chat."

## Goals / Non-Goals

**Goals:**
- Let the admin hand-edit the finalized targeted-resume markdown from the Show page without going back through chat.
- Reuse the existing persistence + artifact-regeneration path (`tailored_data`, DOCX/PDF) rather than inventing a parallel storage format.
- Make the chat agent aware, on its next turn, that the resume was edited outside of chat, and let the admin see that same signal in the UI (so it's not a silent divergence between what's on screen and what the agent "remembers" producing).

**Non-Goals:**
- Editing the resume *before* a first chat-driven finalize (there must be an existing `TargetedResume` row/`tailored_data.markdown` to edit — creation still flows through chat).
- Editing cover letters (same problem, but out of scope for this change).
- Real-time collaborative editing / conflict resolution between the chat agent streaming a new draft and the admin editing concurrently — last-write-wins is acceptable for a single-admin tool.
- Building a generic "AI agent notification" framework; this only wires the one manual-edit signal.

## Decisions

### 1. Markdown editor library: `@uiw/react-md-editor`
MIT-licensed, React 19-compatible (peer dep `>=16.8.0`), actively maintained, ships a live-preview split-pane editor with no CSS framework lock-in, so it slots next to the existing MUI-based admin UI without visual conflicts. Alternatives considered: `react-markdown-editor-lite` (MIT, but less actively maintained, weaker TS types) and building a bespoke `<textarea>` + existing `MarkdownContent` preview (no new dependency, but loses toolbar/keyboard affordances the admin would expect from "an editor"). Given the user explicitly asked for an MIT-licensed package, `@uiw/react-md-editor` is the pick.

### 2. Where the editor lives: a new tab on the existing Show page
`Show.tsx` already tabs between Chat (0) and Details/metadata (1). Add a third tab, "Edit Resume" (or similar), rendered only when `targetedResume !== null` (nothing to edit before a first finalize). This avoids a new route/page and keeps the resume, its chat history, and its metadata co-located, consistent with the existing single-page-per-conversation structure.

### 3. Save path: new endpoint reusing `TargetedResumeService`'s save/regenerate logic
Add `PUT /admin/resume/targeted-resume/{targetedResume}` (adjacent to the existing `download`/`regenerate` routes, which are already keyed by `TargetedResume` rather than `AiConversation`). The controller validates `{ markdown: string }`, updates `tailored_data.markdown` (and `.content`, for parity with what `buildTemplateData` reads), then calls the same DOCX/PDF regeneration `TargetedResumeDocumentService` already exposes via `regenerate()`. This is a small addition to `TargetedResumeService` (e.g. `updateTailoredMarkdown()`) rather than a divergent code path, so DOCX/PDF stays byte-for-byte consistent with what "Finalize" would have produced.

### 4. Agent notification: synthetic `user`-role `AiConversationMessage`, not a new MCP tool
Two options were considered, per the user's framing ("another tool and a message"):

- **(A) Chosen — auto-inserted conversation message.** On save, insert an `AiConversationMessage` with `role: 'user'`, `content` summarizing the edit (e.g. "I manually edited the resume outside of chat. Here is the current version:\n\n<markdown>"), and `metadata: { origin: 'manual_edit', targeted_resume_id }`. This is inserted into history the same way `AiConversationMessage::create()` already works in `startConversation()`/`continueConversation()` — it does **not** trigger an immediate agent turn (no LLM call fired synchronously); it simply becomes part of the history the agent sees the next time the admin sends a chat message. This means the agent's next reply is grounded in the edited text without an extra round-trip or cost, and the admin sees the same message rendered in the transcript (tagged via `metadata.origin` so the frontend can badge it, e.g. "✎ Edited manually" instead of a normal chat bubble).
- **(B) Rejected for this change — a dedicated MCP/tool-call notification.** Registering a new tool (e.g. `notify-resume-edited`) would only matter for an agent that proactively polls/calls tools mid-conversation, which is not how either the in-app chat agent or the external MCP tools operate today (`GetTargetedResumeContextTool` is pull-based: it already reads `tailored_data.markdown` fresh on every call, so external MCP-connected agents automatically see manual edits next time they're asked about this resume — no new tool needed there). Adding a push-style tool would require infrastructure (server-initiated tool call, or a webhook) that doesn't exist for either agent surface, for a case fully covered by (A) + the pull-based tool already existing.

This keeps the "notify the agent" feature to a single, already-proven mechanism (`AiConversationMessage` + `metadata`) instead of introducing new infrastructure.

### 5. Frontend badge for manual edits
`ChatInterface`/message-rendering components read `metadata.origin` (already an unused JSON column) to render manual-edit messages with a distinct style (icon + label) instead of a normal user chat bubble, so the audit trail reads naturally in the transcript rather than looking like the admin typed a chat message.

## Risks / Trade-offs

- **[Risk]** Editing markdown that doesn't match the expected structure could break `MarkdownToOpenXmlConverter` or produce a malformed DOCX. → **Mitigation**: regeneration reuses the exact same converter/service path as `regenerate()`/`finalize()` today, so failure modes are identical to existing behavior; surface `generateDocx()`'s existing `{success, error}` result to the UI instead of silently swallowing failures.
- **[Risk]** Concurrent chat + manual edit: admin edits markdown while the chat agent is mid-stream on a new draft; whichever save lands last wins and the other is lost. → **Mitigation**: accept as a known limitation (single-admin tool, low concurrency risk); optionally warn in the UI if a chat stream is active while the edit tab is open (not required for v1).
- **[Risk]** Synthetic messages inflate token usage on the next agent turn if edits are large/frequent. → **Mitigation**: acceptable trade-off — same cost as the admin pasting the same content into chat manually; no batching/summarization needed for v1.

## Open Questions

- Exact wording/format of the auto-inserted message — final copy can be refined during implementation without affecting the design.
- Whether the "Edit Resume" tab should show a diff against the last chat-finalized version, or just the raw editor — defaulting to raw editor for v1; diffing can be a follow-up if useful in practice.
