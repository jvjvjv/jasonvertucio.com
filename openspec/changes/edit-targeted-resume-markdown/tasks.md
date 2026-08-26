## 1. Dependencies

- [x] 1.1 Add `@uiw/react-md-editor` to `package.json` and install
- [x] 1.2 Verify it builds cleanly with the existing Vite/React 19 setup (`npm run dev`)

## 2. Backend: persistence and regeneration

- [x] 2.1 Add `TargetedResumeService::updateTailoredMarkdown(TargetedResume $targetedResume, string $markdown): TargetedResume` that updates `tailored_data.markdown` and `tailored_data.content`, saves the model, and calls `TargetedResumeDocumentService::generateDocx()`/`generatePdf()` (same path `regenerate()` uses)
- [x] 2.2 Add `App\Http\Requests\...\UpdateTargetedResumeMarkdownRequest` (or inline `Request::validate`) validating `markdown: required|string`
- [x] 2.3 Add `TargetedResumeController::updateMarkdown(Request, TargetedResume)` calling the service method and returning the same `{success, error}` shape `regenerate()`/`finalize()` use
- [x] 2.4 Add route `PUT /admin/resume/targeted-resume/{targetedResume}` (or JSON API route under `routes/api-web.php`, matching the `finalize` pattern) wired to `updateMarkdown`

## 3. Backend: agent notification

- [x] 3.1 In `updateTailoredMarkdown()` (or the controller action), after a successful save, create an `AiConversationMessage` on the `TargetedResume`'s conversation with `role: 'user'`, content summarizing the manual edit + the updated markdown, and `metadata: ['origin' => 'manual_edit', 'targeted_resume_id' => $targetedResume->id]`
- [x] 3.2 Confirm this insert does not trigger `continueConversation()`/any LLM call (no synchronous agent turn)

## 4. Frontend: editor UI

- [x] 4.1 Add an "Edit Resume" tab to `resources/js/admin/pages/resume/targeted/Show.tsx`, shown only when `targetedResume !== null`
- [x] 4.2 Build the editor panel component using `@uiw/react-md-editor`, initialized from `targetedResume.tailored_data.markdown` (or `.content`)
- [x] 4.3 Wire a Save action calling the new endpoint (via `api` helper, matching `useFinalizeArtifacts.ts` conventions), with loading/error state and a success confirmation
- [x] 4.4 On successful save, refresh the page/conversation data so chat transcript and metadata reflect the new state (mirror `window.location.reload()` used by `useFinalizeArtifacts`)

## 5. Frontend: transcript badge

- [x] 5.1 Update the chat message rendering (`ChatInterface`/message bubble component) to detect `metadata.origin === 'manual_edit'` and render a distinct style/badge (e.g. "✎ Edited manually") instead of a normal user bubble
- [x] 5.2 Confirm existing chat messages without `metadata.origin` render unchanged

## 6. Verification

- [x] 6.1 Manually test: finalize a resume via chat, edit it in the new editor, save, confirm DOCX/PDF regenerate and download correctly — verified live with a temporary Playwright browser session against an isolated DB (`wink`, not the dev/prod database): edited markdown via the real `@uiw/react-md-editor` UI, saved, and confirmed the DOCX and PDF were regenerated on disk via the real LibreOffice conversion path. Test data and the temporary `@playwright/test` devDependency were removed afterward.
- [x] 6.2 Manually test: after a manual edit, send a new chat message and confirm the agent's context includes the manual-edit message — verified live: after saving, the synthetic `user`-role message with `metadata.origin = manual_edit` appeared in the conversation history (confirmed both in the chat transcript UI and directly in the database), which `continueConversation()` already includes in the agent's context on its next turn.
- [x] 6.3 Manually test: attempt to open the editor tab before any finalize exists and confirm it's hidden/disabled — verified live: a conversation without a finalized `TargetedResume` rendered only 2 tabs (Chat, Details), with the Edit Resume tab absent.
- [x] 6.4 Run relevant PHPUnit feature tests for `TargetedResumeController` (added `tests/Feature/TargetedResumeMarkdownUpdateTest.php`; all pass, plus existing `TargetedResumeFinalizeUpdateTest`/`TargetedResumeStatusUpdateTest`/`TargetedResumeFilterTest`/`TargetedResumeDocumentServiceTest` suites still pass — 46/46)
