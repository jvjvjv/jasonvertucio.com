## Why

AI Persona (`AiChatBot`) access is currently restricted by Keystone **role** membership (`allowed_roles` + `User::hasAnyRole()`), while every other access-controlled feature in this app (resume editing, unauthenticated-viewer management, admin nav visibility, site settings links) is gated by Keystone **permission** (`$user->can(...)`). Role-based gating on personas is an outlier: it can't be composed across roles the way a permission can (e.g. granting one user access without also granting them everything else that role implies), and it forces admins to manage two different authorization vocabularies for otherwise-identical use cases. Standardizing personas on permission-based access removes that inconsistency and lets an admin grant persona access to a specific user without changing their role.

## What Changes

- **BREAKING**: Replace `AiChatBot.allowed_roles` (JSON array of Keystone role names) with `AiChatBot.required_permission` (nullable string, a Keystone permission name). A persona with no `required_permission` remains public, matching today's "empty `allowed_roles`" behavior.
- Replace `AiChatBot::allowsRole(?User $user): bool` with `AiChatBot::allowsAccess(?User $user): bool`, checking `$user->can($requiredPermission)` instead of `$user->hasAnyRole($allowedRoles)`.
- Update all access-check call sites to the permission check: `CheckChatBotAccess` middleware, `RoleFilteredChatBotIndexPayload`, `RoleFilteredChatBotStatusResolver`, `HostChatBotPagePayload`.
- Update the admin persona editor (`AiChatBotController`, `Store`/`UpdateAiChatBotRequest`, `Create.tsx`/`Edit.tsx`/`Form.tsx`) to pick a single Keystone permission (via `KeystonePermission::pluck('name')`, mirroring `SiteSettingsController`) instead of multiple roles.
- Update persona-facing frontend types/components that read `allowed_roles` (`BotAccessCard.tsx`, `ChatHistoryPanel.tsx`, `ChatBot.tsx`, `Index.tsx`, `resources/js/types/index.ts`) to the new single-permission shape.
- Data migration: add `required_permission` column, backfill it from existing `allowed_roles` data where feasible (best-effort mapping, since roles and permissions aren't 1:1), then drop `allowed_roles`.
- Rename the underlying concept consistently: service/class names referencing "Role" (`RoleFilteredChatBotIndexPayload`, `RoleFilteredChatBotStatusResolver`) should be renamed to reflect permission-based filtering (e.g. `PermissionFilteredChatBotIndexPayload`) for clarity, since the docblocks currently explain the role-based rationale explicitly.

## Capabilities

### New Capabilities

(none — this modifies existing persona-access behavior rather than introducing a new capability)

### Modified Capabilities

- `host-chat-bot-presentation`: its requirements currently codify role-based persona access directly (`allowed_roles`, `AiChatBot::allowsRole()`, "viewer's roles permit/satisfy") across the chat-bot index, statuses endpoint, chat page props, and the package delegation notes. These requirements change to `required_permission` / `AiChatBot::allowsAccess()` / "viewer's permission" throughout.

## Impact

- **Backend**: `app/Models/AiChatBot.php`, `app/Http/Middleware/CheckChatBotAccess.php`, `app/Services/ChatBot/RoleFilteredChatBotIndexPayload.php`, `app/Services/ChatBot/RoleFilteredChatBotStatusResolver.php`, `app/Services/ChatBot/HostChatBotPagePayload.php`, `app/Http/Controllers/Admin/AiChatBotController.php`, `app/Http/Requests/Admin/{Store,Update}AiChatBotRequest.php`.
- **Database**: new migration adding `required_permission` to `ai_personas` (backed by `ai_chat_bots` migration file), backfill migration/step, then a migration dropping `allowed_roles`.
- **Frontend**: `resources/js/admin/pages/ai/bots/{Create,Edit,Form,Index}.tsx`, `resources/js/chat/pages/ai/chat-history-panel/BotAccessCard.tsx`, `resources/js/chat/pages/ai/ChatHistoryPanel.tsx`, `resources/js/chat/pages/ai/ChatBot.tsx`, `resources/js/types/index.ts`.
- **Tests**: `tests/Feature/ChatBotControllerTest.php`, `tests/Feature/HostChatBotPagePayloadTest.php`, `tests/Feature/AiChatBotControllerTest.php` need their role-based fixtures converted to permission-based fixtures.
- **Admins**: existing personas with `allowed_roles` set must be reviewed post-migration since role→permission backfill is best-effort, not guaranteed equivalent.
