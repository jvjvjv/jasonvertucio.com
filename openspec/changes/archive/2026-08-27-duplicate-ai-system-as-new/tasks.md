## 1. Package (jvjvjv/code-talker source, `~/Code/@jvjvjv/code-talker`)

- [x] 1.1 Add a migration adding nullable `duplicated_at` (timestamp) to `ai_systems`
- [x] 1.2 Add `duplicated_at` to `AiSystem`'s `$casts`/fillable as appropriate
- [x] 1.3 Update `AiSystemManager::duplicate()` to set `duplicated_at = now()` on the clone
- [x] 1.4 Add/update package tests covering `AiSystemManager::duplicate()` setting `duplicated_at`
- [x] 1.5 Run the package's full test suite and confirm it's green

## 2. App: backend

- [x] 2.1 Run the new package migration against both app databases (`jasonvertucio` and `wink`, per this repo's dual-DB testing convention)
- [x] 2.2 Update `AiSystemController::duplicate()` to set `duplicated_at = now()` on the clone (or delegate to `AiSystemManager::duplicate()` if that consolidation is taken)
- [x] 2.3 Update `AiSystemController::update()` to: accept submitted `provider`/`model`/`api_key` only when `$aiSystem->duplicated_at !== null`; otherwise keep discarding them as today; clear `duplicated_at` unconditionally on successful save
- [x] 2.4 Update `UpdateAiSystemRequest` to apply `StoreAiSystemRequest`'s provider/model/API-key validation rules only when the route-bound `AiSystem`'s `duplicated_at !== null`
- [x] 2.5 Update `AiSystemController::edit()` to pass a `pendingFirstEdit` boolean Inertia prop derived from `duplicated_at !== null`
- [x] 2.6 Add/update PHPUnit feature tests: duplicate sets `duplicated_at`; update while pending accepts new provider/model/API key; update while pending clears `duplicated_at`; update on an established (non-pending) system still discards provider/model changes as today

## 3. App: frontend

- [x] 3.1 Add `pendingFirstEdit` to the Edit page's Inertia props type and pass it into `AiSystemForm`/`ProviderModelSelector`
- [x] 3.2 Update `ProviderModelSelector.tsx` disable conditions for Provider, Model, and API Key from `disabled={isEdit}` to `disabled={isEdit && !pendingFirstEdit}`
- [x] 3.3 Update the associated helper text so it doesn't claim these fields "cannot be changed" while `pendingFirstEdit` is true
- [x] 3.4 Confirm the API Key field's "leave blank to keep existing value" behavior (reused from Create) works correctly when unlocked on Edit — manually verify submitting the form without touching API Key doesn't blank out the stored credential (verified there is no blank/masked convention — Edit pre-fills the real copied key, and an automated test confirms resubmitting it unchanged round-trips correctly)

## 4. Verification

- [x] 4.1 Manual test: duplicate an AiSystem, confirm Provider/Model/API Key are editable on first Edit view, change them, save, confirm the change persisted (verified via `test_duplicating_a_system_marks_it_as_pending_its_first_edit` + `test_updating_a_pending_duplicate_accepts_new_provider_model_and_api_key`, exercising the real routes end-to-end; not clicked through in a browser — flag if you want that done too)
- [x] 4.2 Manual test: duplicate an AiSystem, save without changing Provider/Model/API Key, confirm a second Edit visit now shows them locked (verified via `test_a_second_update_after_the_first_locks_provider_and_model_again`; not clicked through in a browser)
- [x] 4.3 Manual test: edit an established (non-duplicated) AiSystem, confirm Provider/Model/API Key remain locked exactly as before this change (verified via `test_updating_an_established_system_still_discards_provider_and_model_changes`; not clicked through in a browser)
- [x] 4.4 Run `vendor/bin/phpunit` for the full app suite and confirm no regressions

## 5. Release

- [x] 5.1 Commit and push the package changes in `~/Code/@jvjvjv/code-talker` (done outside this session — the developer committed/released independently, bundled into v0.14.0 alongside an unrelated persona/operator rename)
- [x] 5.2 Tag/publish a new `jvjvjv/code-talker` version including this change (`duplicated_at` shipped in v0.14.0, confirmed present in `AiSystemManager::duplicate()` and the migration)
- [x] 5.3 Bump `composer.json`'s `jvjvjv/code-talker` constraint in this app and run `composer update jvjvjv/code-talker` (constraint changed to `^0` by the developer; `composer update` run, now on v0.14.0)
