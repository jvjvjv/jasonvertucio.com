# Laravel Routes Documentation

This document provides a comprehensive overview of all routes in the jasonvertucio.com application.

---

## Table of Contents

1. [Web Routes](#web-routes)
2. [API Routes](#api-routes)
3. [Keystone Web Routes (Auth)](#keystone-web-routes-auth)
4. [Keystone API Routes (Role Management)](#keystone-api-routes-role-management)
5. [Broadcast Channels](#broadcast-channels)
6. [Console Commands](#console-commands)

---

## Web Routes (`routes/web.php`)

### Public Routes

| Route                | Method | Controller/Action              | Description                                              |
| -------------------- | ------ | ------------------------------ | -------------------------------------------------------- |
| `/login`             | GET    | Closure                        | Login page (Fortify)                                     |
| `/login/check-email` | POST   | `LoginMethodsController@check` | Check available auth methods for email                   |
| `/logout`            | GET    | Closure                        | Logout and redirect to home                              |
| `/` (home)           | GET    | `HomeController@index`         | Homepage with latest blog post, currently watching media |
| `/about/{any?}`      | GET    | Closure                        | Redirects to homepage                                    |
| `/paper`             | View   | Blade view                     | Static paper page                                        |

### Blog Routes (`/blog/*`)

| Route                 | Method | Controller/Action          | Description                    |
| --------------------- | ------ | -------------------------- | ------------------------------ |
| `/blog`               | GET    | `BlogController@index`     | Blog index page (latest posts) |
| `/blog/topics`        | GET    | `BlogController@topics`    | List all blog topics           |
| `/blog/tags`          | GET    | `BlogController@tags`      | List all blog tags             |
| `/blog/topics/{slug}` | GET    | `BlogController@topicList` | Posts filtered by topic slug   |
| `/blog/tags/{slug}`   | GET    | `BlogController@tagList`   | Posts filtered by tag slug     |
| `/blog/{slug}`        | GET    | `BlogController@post`      | Individual blog post           |

### Facebook Callback (Honeypot)

| Route            | Method | Controller/Action                  | Description                        |
| ---------------- | ------ | ---------------------------------- | ---------------------------------- |
| `/mlopnadjs22tn` | ANY    | `FacebookCallbackController@index` | Facebook comments webhook endpoint |

---

### Admin Routes (`/admin/*`)

**Requires:** Authentication + `manage-unauthenticated-viewers` permission

#### Admin Dashboard

| Route    | Method | Controller/Action       | Description          |
| -------- | ------ | ----------------------- | -------------------- |
| `/admin` | GET    | `AdminController@index` | Admin dashboard home |

#### Resume Management (`/admin/resume/*`)

| Route                        | Method | Controller/Action                   | Description           |
| ---------------------------- | ------ | ----------------------------------- | --------------------- |
| `/admin/resume`              | GET    | `AdminController@resumeHub`         | Resume management hub |
| `/admin/resume/codes`        | GET    | `ResumeShareCodeController@index`   | List share codes      |
| `/admin/resume/codes`        | POST   | `ResumeShareCodeController@store`   | Create new share code |
| `/admin/resume/codes/{code}` | DELETE | `ResumeShareCodeController@destroy` | Delete share code     |

#### Cover Letter Management (`/admin/cover-letters/*`)

| Route                                     | Method | Controller/Action                    | Description                  |
| ----------------------------------------- | ------ | ------------------------------------ | ---------------------------- |
| `/admin/cover-letters`                    | GET    | `CoverLetterController@index`        | List cover letters           |
| `/admin/cover-letters/new`                | GET    | `CoverLetterController@create`       | Create new cover letter form |
| `/admin/cover-letters`                    | POST   | `CoverLetterController@store`        | Store new cover letter       |
| `/admin/cover-letters/{id}`               | GET    | `CoverLetterController@edit`         | Edit cover letter            |
| `/admin/cover-letters/{id}`               | PUT    | `CoverLetterController@update`       | Update cover letter          |
| `/admin/cover-letters/{id}`               | DELETE | `CoverLetterController@destroy`      | Delete cover letter          |
| `/admin/cover-letters/{id}/preview`       | GET    | `CoverLetterController@preview`      | Preview cover letter         |
| `/admin/cover-letters/{id}/download/docx` | GET    | `CoverLetterController@downloadDocx` | Download as DOCX             |
| `/admin/cover-letters/{id}/download/pdf`  | GET    | `CoverLetterController@downloadPdf`  | Download as PDF              |

#### Mail Preview (`/admin/mail-preview/*`)

| Route                                    | Method | Controller/Action               | Description            |
| ---------------------------------------- | ------ | ------------------------------- | ---------------------- |
| `/admin/mail-preview`                    | GET    | `MailPreviewController@index`   | List mail templates    |
| `/admin/mail-preview/{mailable}`         | GET    | `MailPreviewController@show`    | View specific mailable |
| `/admin/mail-preview/{mailable}/preview` | GET    | `MailPreviewController@preview` | Preview mailable       |

#### Site Settings (`/admin/site-settings/*`)

| Route                  | Method | Controller/Action               | Description           |
| ---------------------- | ------ | ------------------------------- | --------------------- |
| `/admin/site-settings` | GET    | `SiteSettingsController@edit`   | Edit navigation links |
| `/admin/site-settings` | POST   | `SiteSettingsController@update` | Save site settings    |

---

### AI Tools Routes (`/admin/ai/*`)

**Requires:** Authentication + `manage-ai-tools` permission

#### AI Tools Dashboard

| Route       | Method | Controller/Action         | Description        |
| ----------- | ------ | ------------------------- | ------------------ |
| `/admin/ai` | GET    | `AiToolsController@index` | AI tools dashboard |

#### AI Systems (`/admin/ai/systems/*`)

| Route                              | Method | Controller/Action                | Description               |
| ---------------------------------- | ------ | -------------------------------- | ------------------------- |
| `/admin/ai/systems`                | GET    | `AiSystemController@index`       | List AI systems           |
| `/admin/ai/systems/new`            | GET    | `AiSystemController@create`      | Create new AI system form |
| `/admin/ai/systems`                | POST   | `AiSystemController@store`       | Store new AI system       |
| `/admin/ai/systems/{id}`           | GET    | `AiSystemController@edit`        | Edit AI system            |
| `/admin/ai/systems/{id}`           | PUT    | `AiSystemController@update`      | Update AI system          |
| `/admin/ai/systems/{id}/duplicate` | POST   | `AiSystemController@duplicate`   | Duplicate AI system       |
| `/admin/ai/systems/{id}`           | DELETE | `AiSystemController@destroy`     | Delete AI system          |
| `/admin/ai/systems/{id}/logs`      | GET    | `AiSystemController@logs`        | View AI system logs       |
| `/admin/ai/systems/fetch-models`   | POST   | `AiSystemController@fetchModels` | Fetch available models    |

#### AI Memories (`/admin/ai/memories/*`)

| Route                                  | Method | Controller/Action            | Description            |
| -------------------------------------- | ------ | ---------------------------- | ---------------------- |
| `/admin/ai/memories`                   | GET    | `AiMemoryController@index`   | List AI memories       |
| `/admin/ai/memories/new`               | GET    | `AiMemoryController@create`  | Create new memory form |
| `/admin/ai/memories`                   | POST   | `AiMemoryController@store`   | Store new memory       |
| `/admin/ai/memories/{id}`              | GET    | `AiMemoryController@edit`    | Edit memory            |
| `/admin/ai/memories/{id}`              | PUT    | `AiMemoryController@update`  | Update memory          |
| `/admin/ai/memories/{id}`              | DELETE | `AiMemoryController@destroy` | Delete memory          |
| `/admin/ai/memories/rebuild/{feature}` | POST   | `AiMemoryController@rebuild` | Rebuild memory index   |

#### AI Conversations (`/admin/ai/conversations/*`)

| Route                             | Method | Controller/Action                  | Description                     |
| --------------------------------- | ------ | ---------------------------------- | ------------------------------- |
| `/admin/ai/conversations`         | GET    | `AiConversationController@index`   | List AI conversations           |
| `/admin/ai/conversations/{id}`    | GET    | `AiConversationController@show`    | View AI conversation details    |
| `/admin/ai/conversations/{id}`    | DELETE | `AiConversationController@destroy` | Delete AI conversation          |

#### AI Chat Bots (`/admin/ai/chat-bots/*`)

| Route                            | Method | Controller/Action              | Description                 |
| -------------------------------- | ------ | ------------------------------ | --------------------------- |
| `/admin/ai/chat-bots`            | GET    | `AiChatBotController@index`    | List AI chat bots           |
| `/admin/ai/chat-bots/new`        | GET    | `AiChatBotController@create`   | Create new AI chat bot form |
| `/admin/ai/chat-bots`            | POST   | `AiChatBotController@store`    | Store new AI chat bot       |
| `/admin/ai/chat-bots/{id}`       | GET    | `AiChatBotController@edit`     | Edit AI chat bot            |
| `/admin/ai/chat-bots/{id}`       | PUT    | `AiChatBotController@update`   | Update AI chat bot          |
| `/admin/ai/chat-bots/{id}`       | DELETE | `AiChatBotController@destroy`  | Delete AI chat bot          |

---

### Public AI Chat Bot Routes (`/chat/*`)

| Route                      | Method | Controller/Action           | Description                           |
| -------------------------- | ------ | --------------------------- | ------------------------------------- |
| `/chat/{slug}`             | GET    | `ChatBotController@show`    | Display the public/authenticated bot  |
| `/chat/{slug}/messages`    | POST   | `ChatBotController@message` | Send a chat message and stream reply  |
| `/chat/{slug}/reset`       | POST   | `ChatBotController@reset`   | Reset the current bot conversation    |

---

### Resume Editor Routes (`/admin/resume/*`)

**Requires:** Authentication + `edit-resume` permission

#### Resume Editor Hub

| Route                  | Method | Controller/Action               | Description                       |
| ---------------------- | ------ | ------------------------------- | --------------------------------- |
| `/admin/resume/editor` | GET    | `ResumeEditorController@edit`   | Resume editor dashboard           |
| `/admin/resume/editor` | POST   | `ResumeEditorController@update` | Save resume data (all JSON files) |

#### Targeted Resume Builder (`/admin/resume/targeted-builder/*`)

| Route                                                                 | Method | Controller/Action                              | Description                        |
| --------------------------------------------------------------------- | ------ | ---------------------------------------------- | ---------------------------------- |
| `/admin/resume/targeted-builder`                                      | GET    | `TargetedResumeController@index`               | Targeted resume builder dashboard  |
| `/admin/resume/targeted-builder/new`                                  | GET    | `TargetedResumeController@create`              | Start new targeted resume          |
| `/admin/resume/targeted-builder/start`                                | POST   | `TargetedResumeController@start`               | Initialize targeted resume session |
| `/admin/resume/targeted-builder/{conversation}`                       | GET    | `TargetedResumeController@show`                | View conversation with AI          |
| `/admin/resume/targeted-builder/{conversation}/metadata`              | PUT    | `TargetedResumeController@updateMetadata`      | Update target job metadata         |
| `/admin/resume/targeted-builder/{conversation}/chat`                  | POST   | `TargetedResumeController@chat`                | Send chat message to AI            |
| `/admin/resume/targeted-builder/{conversation}/finalize`              | POST   | `TargetedResumeController@finalize`            | Finalize targeted resume           |
| `/admin/resume/targeted-builder/{conversation}/finalize-cover-letter` | POST   | `TargetedResumeController@finalizeCoverLetter` | Generate cover letter              |
| `/admin/resume/targeted-builder/{conversation}/pass`                  | POST   | `TargetedResumeController@pass`                | Pass to review stage               |
| `/admin/resume/targeted-builder/{conversation}`                       | DELETE | `TargetedResumeController@destroy`             | Delete conversation                |

#### Job URL Parsing (`/admin/resume/targeted-builder/*`)

| Route                                                    | Method | Controller/Action                     | Description    |
| -------------------------------------------------------- | ------ | ------------------------------------- | -------------- |
| `/admin/resume/targeted-builder/parse-url`               | POST   | `JobUrlParseController@parse`         | Parse job URL  |
| `/admin/resume/targeted-builder/parser/{parser}/confirm` | POST   | `JobUrlParseController@confirmParser` | Confirm parser |
| `/admin/resume/targeted-builder/parser/{parser}/reject`  | POST   | `JobUrlParseController@rejectParser`  | Reject parser  |
| `/admin/resume/targeted-builder/parser/{parser}/reparse` | POST   | `JobUrlParseController@reparse`       | Reparse URL    |

#### Targeted Resume Downloads & Regeneration

| Route                                                  | Method | Controller/Action                     | Description              |
| ------------------------------------------------------ | ------ | ------------------------------------- | ------------------------ |
| `/admin/resume/targeted-resume/{id}/download/{format}` | GET    | `TargetedResumeController@download`   | Download targeted resume |
| `/admin/resume/targeted-resume/{id}/regenerate`        | POST   | `TargetedResumeController@regenerate` | Regenerate DOCX/PDF      |

---

### Resume Public Routes (`/resume/*`)

**Supported by:** Share code middleware (supports auth + share codes)

| Route                | Method | Controller/Action            | Description                                   |
| -------------------- | ------ | ---------------------------- | --------------------------------------------- |
| `/resume`            | GET    | `ResumeController@index`     | View resume (with share code input if needed) |
| `/resume/enter-code` | GET    | `ResumeController@enterCode` | Manual code entry page                        |

#### Resume Downloads (`/resume/download/*`)

| Route                   | Method | Controller/Action               | Description                                |
| ----------------------- | ------ | ------------------------------- | ------------------------------------------ |
| `/resume/download`      | GET    | `ResumeController@download`     | Download pre-generated DOCX (all versions) |
| `/resume/download/docx` | GET    | `ResumeController@downloadDocx` | Direct DOCX download                       |
| `/resume/download/pdf`  | GET    | `ResumeController@downloadPdf`  | Direct PDF download                        |

---

### Honeypot Routes

| Route           | Method   | Controller/Action           | Description                          |
| --------------- | -------- | --------------------------- | ------------------------------------ |
| `/wp-admin`     | Redirect | Closure                     | Redirects to wp-login.php (honeypot) |
| `/wp-login.php` | GET      | `WordpressController@index` | WordPress honeypot page              |
| `/wp-login.php` | POST     | `WordpressController@ban`   | Ban IP from honeypot form            |

---

## API Routes (`routes/api.php`)

### Jellyfin Media Integration

| Route                     | Method | Controller/Action                        | Description                          |
| ------------------------- | ------ | ---------------------------------------- | ------------------------------------ |
| `/api/event/@2028`        | POST   | `LocalMediaController@index`             | Receive webhook events from Jellyfin |
| `/api/currently-watching` | GET    | `LocalMediaController@currentlyWatching` | Get currently playing media info     |
| `/api/media-stats`        | GET    | `LocalMediaController@mediaStats`        | Get media statistics                 |

---

## Keystone Web Routes (Auth) (`routes/keystone-web.php`)

These routes come from BSPDX Keystone package and handle authentication.

### Profile Routes

**Middleware:** `web`, `auth`

| Route                       | Method | Controller/Action                         | Description                             |
| --------------------------- | ------ | ----------------------------------------- | --------------------------------------- |
| `/profile`                  | GET    | `ProfileController@show`                  | Show profile page with auth preferences |
| `/profile/auth-preferences` | PUT    | `ProfileController@updateAuthPreferences` | Update authentication preferences       |

### Passwordless Login Routes

**Middleware:** `web`, `guest`

| Route            | Method | Controller/Action                      | Description                          |
| ---------------- | ------ | -------------------------------------- | ------------------------------------ |
| `/login/methods` | POST   | `LoginController@getAuthMethods`       | Get available auth methods for email |
| `/login/totp`    | POST   | `LoginController@authenticateWithTotp` | Authenticate with 2FA code           |

### Two-Factor Authentication Routes

**Middleware:** `web`, `auth`

| Route                                       | Method | Controller/Action                                 | Description         |
| ------------------------------------------- | ------ | ------------------------------------------------- | ------------------- |
| `/user/two-factor-authentication`           | GET    | `TwoFactorAuthController@create`                  | Enable 2FA form     |
| `/user/two-factor-authentication`           | POST   | `TwoFactorAuthController@store`                   | Store enabled 2FA   |
| `/user/confirmed-two-factor-authentication` | POST   | `TwoFactorAuthController@confirm`                 | Confirm 2FA setup   |
| `/user/two-factor-authentication`           | DELETE | `TwoFactorAuthController@destroy`                 | Disable 2FA         |
| `/user/two-factor-recovery-codes`           | GET    | `TwoFactorAuthController@recoveryCodes`           | Show recovery codes |
| `/user/two-factor-recovery-codes`           | POST   | `TwoFactorAuthController@regenerateRecoveryCodes` | Regenerate codes    |

### Passkey Routes

**Middleware:** `web` (guest & authenticated sections)

#### Guest Operations

| Route                    | Method | Controller/Action                    | Description               |
| ------------------------ | ------ | ------------------------------------ | ------------------------- |
| `/passkey/login`         | GET    | `PasskeyAuthController@loginView`    | Passkey login form        |
| `/passkey/login/options` | POST   | `PasskeyAuthController@loginOptions` | Get passkey login options |
| `/passkey/authenticate`  | POST   | `PasskeyAuthController@authenticate` | Authenticate with passkey |

#### Authenticated Operations

**Middleware:** `web`, `auth`

| Route                    | Method | Controller/Action                       | Description                  |
| ------------------------ | ------ | --------------------------------------- | ---------------------------- |
| `/user/passkeys`         | GET    | `PasskeyAuthController@registerView`    | Register new passkey form    |
| `/user/passkeys/options` | POST   | `PasskeyAuthController@registerOptions` | Get passkey register options |
| `/user/passkeys`         | POST   | `PasskeyAuthController@store`           | Store registered passkey     |
| `/user/passkeys/{id}`    | DELETE | `PasskeyAuthController@destroy`         | Delete passkey               |

---

## Keystone API Routes (Role Management) (`routes/keystone-api.php`)

These routes come from BSPDX Keystone package and require Sanctum authentication.

### Role & Permission Management API

**Middleware:** `auth:sanctum`
**Prefix:** `/api`

| Route                                 | Method | Controller/Action                                  | Description                | Permission Required  |
| ------------------------------------- | ------ | -------------------------------------------------- | -------------------------- | -------------------- |
| `/api/roles`                          | GET    | `RolePermissionController@roles`                   | List all roles             | `view-roles`         |
| `/api/permissions`                    | GET    | `RolePermissionController@permissions`             | List all permissions       | `view-permissions`   |
| `/api/roles`                          | POST   | `RolePermissionController@createRole`              | Create new role            | `create-roles`       |
| `/api/permissions`                    | POST   | `RolePermissionController@createPermission`        | Create new permission      | `create-permissions` |
| `/api/roles/{role}`                   | DELETE | `RolePermissionController@deleteRole`              | Delete role                | `delete-roles`       |
| `/api/permissions/{permission}`       | DELETE | `RolePermissionController@deletePermission`        | Delete permission          | `delete-permissions` |
| `/api/users/{user}/roles`             | POST   | `RolePermissionController@assignRoles`             | Assign roles to user       | `assign-roles`       |
| `/api/users/{user}/permissions`       | POST   | `RolePermissionController@assignPermissions`       | Assign permissions to user | `assign-permissions` |
| `/api/users/{user}/roles-permissions` | GET    | `RolePermissionController@userRolesPermissions`    | Get user roles/permissions | `view-users`         |
| `/api/roles/{role}/permissions`       | POST   | `RolePermissionController@assignPermissionsToRole` | Assign permissions to role | `assign-permissions` |

---

### Two-Factor Authentication API

**Middleware:** `auth:sanctum`
**Prefix:** `/api/user`

| Route                                  | Method | Controller/Action                                 | Description              |
| -------------------------------------- | ------ | ------------------------------------------------- | ------------------------ |
| `/two-factor-authentication`           | POST   | `TwoFactorAuthController@store`                   | Enable 2FA (API)         |
| `/confirmed-two-factor-authentication` | POST   | `TwoFactorAuthController@confirm`                 | Confirm 2FA (API)        |
| `/two-factor-authentication`           | DELETE | `TwoFactorAuthController@destroy`                 | Disable 2FA (API)        |
| `/two-factor-recovery-codes`           | GET    | `TwoFactorAuthController@recoveryCodes`           | Get recovery codes (API) |
| `/two-factor-recovery-codes`           | POST   | `TwoFactorAuthController@regenerateRecoveryCodes` | Regenerate codes (API)   |

---

### Passkey API

**Middleware:** `auth:sanctum`
**Prefix:** `/api/user`

| Route               | Method | Controller/Action                       | Description                |
| ------------------- | ------ | --------------------------------------- | -------------------------- |
| `/passkeys`         | GET    | `PasskeyAuthController@index`           | List passkeys (API)        |
| `/passkeys/options` | POST   | `PasskeyAuthController@registerOptions` | Get register options (API) |
| `/passkeys`         | POST   | `PasskeyAuthController@store`           | Register passkey (API)     |
| `/passkeys/{id}`    | DELETE | `PasskeyAuthController@destroy`         | Delete passkey (API)       |

---

## Broadcast Channels (`routes/channels.php`)

Event broadcasting channels using Laravel Reverb/Pusher.

| Channel         | Authorization          | Description                       |
| --------------- | ---------------------- | --------------------------------- |
| `App.User.{id}` | User must match the ID | Private channel for specific user |

---

## Console Commands (`routes/console.php`)

Artisan CLI commands (Closure-based):

| Command               | Description                |
| --------------------- | -------------------------- |
| `php artisan inspire` | Display an inspiring quote |

---

## Route Groups & Middleware Summary

### Global Middleware Applied by Route Type

| Route Group      | Middleware                                              |
| ---------------- | ------------------------------------------------------- |
| **Web Routes**   | `web`, `guest` (when specified)                         |
| **API Routes**   | `api` + Sanctum auth where applicable                   |
| **Admin Routes** | `auth` + specific permissions + `HandleInertiaRequests` |

### Permission Requirements for Admin Routes

| Route Group            | Permissions Required             |
| ---------------------- | -------------------------------- |
| `/admin/*`             | `manage-unauthenticated-viewers` |
| `/admin/ai/*`          | `manage-ai-tools`                |
| `/admin/resume/editor` | `edit-resume`                    |

---

## Route Naming Conventions

Routes follow these naming patterns:

- Public routes: Simple names like `home`, `blog`, `post`
- Blog routes: `blog`, `topics`, `tags`, `topicList`, `tagList`, `post`
- Admin routes: `admin.resume.*`, `admin.cover-letters.*`, etc.
- AI routes: `admin.ai.systems.*`, `admin.ai.memories.*`, `admin.ai.conversations.*`, `admin.ai.bots.*`, `chat-bots.*`
- Keystone routes: `keystone.profile.show`, `two-factor.enable`, `passkeys.login`
- API routes: `api.roles.index`, `api.two-factor.store`

---

**Generated:** 2026-04-11  
**Laravel Version:** 12.x  
**Package Versions:** Canvas v6.0.55, Keystone BSPDX package
