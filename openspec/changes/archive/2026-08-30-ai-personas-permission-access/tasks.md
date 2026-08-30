## 1. Database

- [x] 1.1 Add migration: nullable `required_permission` string column on `ai_personas` (file backing `ai_chat_bots`/`ai_personas`)
- [x] 1.2 Write a backfill step (artisan command or migration data step) that maps each persona's `allowed_roles` to `required_permission` per the developer-confirmed mapping rule; leave `required_permission` null and log/flag any persona with no clear mapping
- [x] 1.3 Run the backfill against dev DB (`wink`, per project memory) and manually review flagged personas with the developer before proceeding
- [x] 1.4 Add a final migration dropping `allowed_roles` from `ai_personas` (ran on both DBs — see note below on unexpected timing)
- [x] 1.5 Run `DB_DATABASE=wink php artisan migrate` in addition to the default DB migration, per project convention for keeping both databases in sync

## 2. Backend model & authorization

- [x] 2.1 In `app/Models/AiChatBot.php`: replace `allowed_roles` fillable/cast with `required_permission` (string, nullable — no cast needed)
- [x] 2.2 Rename `allowsRole(?User $user): bool` to `allowsAccess(?User $user): bool`, checking `$user->can($this->required_permission)` instead of `hasAnyRole()`; keep the "no restriction configured = public" short-circuit
- [x] 2.3 Update `app/Http/Middleware/CheckChatBotAccess.php` to check `required_permission`/`allowsAccess()` instead of `allowed_roles`/`hasAnyRole()`

## 3. Backend services

- [x] 3.1 Rename `app/Services/ChatBot/RoleFilteredChatBotIndexPayload.php` → `PermissionFilteredChatBotIndexPayload.php`, update its `canAccess()` to use `required_permission`/`allowsAccess()`, and rewrite its docblock (role rationale → permission rationale)
- [x] 3.2 Rename `app/Services/ChatBot/RoleFilteredChatBotStatusResolver.php` → `PermissionFilteredChatBotStatusResolver.php` with the same `canAccess()` update and docblock rewrite
- [x] 3.3 Update all references to the renamed classes (constructor injection in `ChatBotController`, any container bindings, imports)
- [x] 3.4 Update `app/Services/ChatBot/HostChatBotPagePayload.php` to expose `required_permission` (nullable string) instead of `allowed_roles` (array) on the `bot` page prop

## 4. Admin persona management

- [x] 4.1 In `app/Http/Controllers/Admin/AiChatBotController.php`: replace the `roles()` helper (`KeystoneRole::pluck('name')`) with a `permissions()` helper (`KeystonePermission::pluck('name')`), mirroring `SiteSettingsController::edit()`; update the `Create`/`Edit` Inertia props from `roles` to `permissions`, and the `allowed_roles`/`required_permission` value passed to the edit form
- [x] 4.2 Update `app/Http/Requests/Admin/StoreAiChatBotRequest.php` and `UpdateAiChatBotRequest.php`: replace `allowed_roles`/`allowed_roles.*` array rules with a single `required_permission` rule (`nullable`, `string`, `in:` the live `KeystonePermission` name list, matching `SiteSettingsController::update()`'s `allowedCan` pattern)

## 5. Frontend

- [x] 5.1 Update `resources/js/types/index.ts`: replace `allowed_roles?: string[]` with `required_permission?: string | null` on the relevant bot type(s)
- [x] 5.2 Update `resources/js/admin/pages/ai/bots/Form.tsx`: replace the multi-select role-chip UI with a single permission select/combobox bound to `required_permission`, sourced from the `permissions` prop
- [x] 5.3 Update `resources/js/admin/pages/ai/bots/Create.tsx` and `Edit.tsx`: replace `allowed_roles: [...]` initial form data with `required_permission: null`, and the `roles` prop with `permissions`
- [x] 5.4 Update `resources/js/admin/pages/ai/bots/Index.tsx`: replace the `allowed_roles.length`/`.join(", ")` tooltip logic with a `required_permission` null-check and display
- [x] 5.5 Update `resources/js/chat/pages/ai/chat-history-panel/BotAccessCard.tsx`: replace the `allowed_roles: string[]` prop and its length-based public/restricted branching with `required_permission: string | null`
- [x] 5.6 Update `resources/js/chat/pages/ai/ChatHistoryPanel.tsx` and `resources/js/chat/pages/ai/ChatBot.tsx`: replace `allowed_roles: string[]` in the bot summary type with `required_permission: string | null`

## 6. Tests

- [x] 6.1 Update `tests/Feature/ChatBotControllerTest.php`: convert role-based bot fixtures/assertions (public vs. `admin`/`editor`-restricted) to permission-based (public vs. restricted to a specific Keystone permission)
- [x] 6.2 Update `tests/Feature/HostChatBotPagePayloadTest.php`: assert `bot.required_permission` instead of `bot.allowed_roles`
- [x] 6.3 Update `tests/Feature/AiChatBotControllerTest.php`: convert admin CRUD tests to create/update personas via `required_permission` instead of `allowed_roles`
- [x] 6.4 Grep the codebase and tests for any remaining `allowed_roles`, `allowsRole`, or `RoleFilteredChatBot*` references and remove/update them (also updated `database/factories/AiChatBotFactory.php`, not originally listed but required)
- [x] 6.5 Run `php artisan test --compact --filter=ChatBot` and `--filter=AiChatBot` to confirm the updated suites pass (ran via `vendor/bin/phpunit --filter=...`, since `php artisan test` is not registered in this app; 66 + 13 tests pass)
- [x] 6.6 Ask the developer whether to run the full test suite (`php artisan test --compact`) before finalizing — developer said yes; ran via `vendor/bin/phpunit`: 366 tests, 1 pre-existing failure unrelated to this change (`LmStudioServiceTest::test_list_models_exposes_capability_metadata`, missing `size_bytes` key — model-metadata feature, not persona access)

## 7. Spec sync

- [x] 7.1 After implementation and tests pass, sync the `host-chat-bot-presentation` delta spec into `openspec/specs/host-chat-bot-presentation/spec.md` (`/opsx:sync` or equivalent)
