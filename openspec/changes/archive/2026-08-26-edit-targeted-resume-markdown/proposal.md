## Why

The targeted resume builder currently only produces resume markdown through the chat-driven AI agent. When the model's output needs a small correction (wording, a stray bullet, a fact the AI got wrong), the only way to fix it today is to keep prompting the agent until it produces the right text, which is slow and sometimes unreliable. The admin needs a direct way to hand-edit the finalized markdown, and the agent needs to find out when that happens so it doesn't unknowingly work from stale content on the next turn.

## What Changes

- Add a markdown editor to the Targeted Resume admin "Show" page for editing the finalized (`tailored_data`) resume content directly, gated on a targeted resume already existing (i.e. something has been finalized at least once via chat).
- Introduce a save action that persists edited markdown to the same `tailored_data.content`/`markdown` field the chat "finalize" flow writes to, and regenerates the DOCX/PDF artifacts the same way `regenerate` does today.
- On save, automatically append a synthetic `user`-role message to the conversation (`AiConversationMessage`, tagged via `metadata.origin = 'manual_edit'`) summarizing that the resume was hand-edited outside the chat, so the in-app chat agent sees this context on its next turn. The message is inserted into history without triggering an immediate agent turn.
- Render manually-edited messages distinctly in the chat transcript (e.g. an "edited manually" badge) so the audit trail is visible to the admin.
- Add an MIT-licensed React markdown editor dependency (`@uiw/react-md-editor`) to `package.json`.

## Capabilities

### New Capabilities
- `targeted-resume-manual-editing`: Direct markdown editing of a finalized targeted resume outside the chat flow, including persistence, artifact regeneration, and notifying the chat agent of the out-of-band change.

### Modified Capabilities
(none — no existing spec covers targeted resume behavior yet)

## Impact

- **Backend**: New controller action + route under `routes/admin-resume.php` / `routes/api-web.php` (e.g. `PUT /admin/resume/targeted-resume/{targetedResume}`); extends `TargetedResumeService` (or a small sibling method) to reuse the existing save/regenerate path used by `finalize()`/`regenerate()`; inserts an `AiConversationMessage` with `metadata.origin = 'manual_edit'`.
- **Frontend**: New editor UI on `resources/js/admin/pages/resume/targeted/Show.tsx` (likely a third tab or inline toggle on the existing metadata/chat tabs); new `@uiw/react-md-editor` dependency; chat transcript rendering picks up the `metadata.origin` tag to badge manual-edit messages.
- **Data**: No schema changes — reuses existing `tailored_data` JSON column on `targeted_resumes` and existing `metadata` JSON column on `ai_conversation_messages`.
- **Dependencies**: Adds `@uiw/react-md-editor` (MIT) to `package.json`.
