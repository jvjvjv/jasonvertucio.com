## Context

See proposal.md for motivation; the specs (`admin-hubs`, `company-intel`, `application-tracking`) hold the requirements. What shapes the approach:

- **Navigation is data.** `resources/js/admin/navigation.json` lists each admin area (`route`, `name`, `can`, `navigationItems`). `AdminNavigationService` filters it per user for hub tiles (`getNavBlocksForRoute`) and the app bar (`getAppBarItems`); hub pages (`Dashboard.tsx`, `resume/Hub.tsx`, `ai/Index.tsx`) are thin wrappers around `NavGrid`. The Blog Admin tile checks `manage-blogs`, a permission that does not exist (the seeded one is `manage-blog`), so that tile has never been shown.
- **Route gates differ by group.** `/admin/*` in `routes/admin.php` sits behind `can:manage-unauthenticated-viewers`; comment moderation is a separate group behind the `moderate-comments` gate (`manage-comments` OR `manage-blog`); application pages are behind `can:edit-resume` in `routes/admin-resume.php`.
- **AI sessions.** `TargetedResumeService::continueConversation()` streams one turn as SSE: it rebuilds history from `AiConversationMessage`s, builds an agent with `AgentFactory::forSystem()` and the host's `TargetedResumeToolRegistry` tools, logs `AiLlmMessage`s and stores the assistant message. code-talker's `AiOperatorRunner` shows the non-streaming pattern (no browser listening) and builds granted web tools with `new ChatBotToolRegistry($conversation, $system->allowed_tools)`. code-talker must not be edited.
- **Prompts and defaults.** System prompts are `AiSystemPrompt` rows found by hard-coded ids (`PROMPT_ID_TARGETED_RESUME = 4`, …). Production ids are not guaranteed to match a new hard-coded id (local has a gap at 6 and rows at 7–8). Feature defaults are `AiSystemFeatureDefault` rows; the accepted keys are hard-coded in `StoreAiSystemRequest`/`UpdateAiSystemRequest` (`in:targeted-resume,cover-letter`) and `ALL_FEATURES` in `ai/systems/Form.tsx`.
- **Queues.** `QUEUE_CONNECTION=database`; production runs one `queue:work --timeout=299 --tries=3` worker. Nothing but mail is queued today.
- **Tool discovery.** `AppServiceProvider` registers the whole `app/Services/Mcp/Tools` tree with CodeTalker, so any tool placed there is also grantable to chat bots by name.
- **UI constraints.** MUI admin app; no gradients or box shadows (CLAUDE.md); no sweeping visual changes — new UI must look like the existing admin pages, and screenshots go to the user before merge.

## Goals / Non-Goals

**Goals:**
- Hubs are pure navigation-data changes plus two thin pages, reusing `NavGrid`.
- Company Intel's records are written only by tools the agent calls, so findings are structured and the chat stays the audit trail.
- One code path runs an intel turn, used both streamed (Discussion-page start, follow-ups, re-research) and in the background (automatic and list starts).
- Starting intel can never break marking an application applied.

**Non-Goals:**
- Moving existing page URLs under `/admin/employment` (only new pages live there).
- Editing Company or Role intel fields by hand (the chat is the edit surface; a later change can add forms).
- A Companies directory page, merging duplicate companies, or refreshing stale company data on a schedule.
- Changing the targeted-resume system prompt or tools.

## Decisions

### 1. Hubs are entries in navigation.json

Add `/admin/employment` ("Employment", `appBar: true`, `can: null`) and `/admin/blog` ("Blog", `appBar: true`, `can: "moderate-comments"`) entries. The Employment entry is placed directly after the Admin Dashboard entry so the app bar lists it first. The dashboard's items become: Employment, Mail Preview, AI Tools, Roles & Permissions, Site Navigation, Blog (`can: moderate-comments`).

The Employment hub's own route needs no extra gate beyond the `/admin` group (its tiles are filtered individually), but the dashboard tile uses `can: "edit-resume"` so users who would see an empty hub are not offered it. Cover Letter Management inside the hub keeps its current `edit-resume` item check (its routes stay behind `manage-unauthenticated-viewers`, unchanged). Company Intel is `can: "edit-resume"`.

The Blog hub route goes in the existing `moderate-comments` group in `routes/admin.php`, not the `/admin` group, so a comment moderator without `manage-unauthenticated-viewers` can still reach it. Its Blog Admin item is corrected to `can: "manage-blog"` and `external: true`.

`AdminController` gains `employmentHub()` and `blogHub()`; pages `employment/Hub.tsx` and `blog/Hub.tsx` copy `resume/Hub.tsx`. *Alternative considered:* one generic `Hub.tsx` taking a title — rejected for now because the three existing hubs are separate files and consolidating them is a refactor that should not ride along with this change.

### 2. Data model

```
companies            id, name, website_domain (unique, nullable), website_url, headquarters,
                     employee_count (string — ranges like "1,001–5,000"), revenue (string — "$1.2B (FY2025)"),
                     industry, founded_year (smallint), ownership (public|private|nonprofit|government|null),
                     ticker, summary (text), researched_at, timestamps
company_insights     id, company_id → companies cascadeOnDelete, category (enum string), title,
                     content (text, Markdown), source_url (nullable), timestamps
role_intels          id, application_id → applications (unique), company_id → companies nullOnDelete,
                     ai_conversation_id (unique, nullable), status (queued|running|ready|failed),
                     failure_reason (text), summary, responsibilities, requirements, team, compensation,
                     interview_process, concerns (all text, Markdown, nullable),
                     rejected_companies (json: [{name, domain}]), timestamps
```

- Employee count and revenue are strings, not numbers: public sources give ranges and fiscal-year qualifiers, and these fields are read, never computed on.
- Company identity is the normalized website domain (lowercased, `www.` stripped). Name-only companies (domain unknown) are matched case-insensitively on name when attaching; the unique index is on domain only, since distinct companies share names.
- `role_intels.application_id` references the application without cascade: applications are soft-deleted, and queries filter through a non-trashed application.
- `rejected_companies` is a JSON list on the Role intel rather than a table — it is consulted only by that Role intel's tools and is small.
- Models: `Company` (hasMany insights, hasMany roleIntels), `CompanyInsight`, `RoleIntel` (belongsTo application, company, conversation), `Application::roleIntel()` hasOne. Enums `RoleIntelStatus`, `CompanyInsightCategory`. Factories for all three.

### 3. The Company Intel prompt is a fixed-id prompt, like the others

The prompt joins the existing seeded set (Default 1, Creative 2, Technical 3, Targeted Resume 4, Cover Letter 5) as **id 6**: a `PROMPT_ID_COMPANY_INTEL = 6` constant beside them, looked up with `AiSystemPrompt::find(PROMPT_ID_COMPANY_INTEL)`, seeded by a data migration in the same shape as `2026_05_27_150002_seed_default_ai_system_prompts` — title "Company Intel Prompt", a description, and starter content from a `buildCompanyIntelPrompt()` method (the analogue of `buildResumePortionPrompt()`), which is also the fallback when the row is missing. Looking it up by id is what lets the admin rename it freely.

**Ids 1–49 are reserved for built-in prompts; admin-made prompts start at 50.** Today admin-made prompts take the next auto-increment id straight after the built-ins (locally: 7 "Mock Interviewer" and 8 "Bonsai…", and AI system 11 points at 8), so every new built-in risks colliding with one in production. A `reserve_builtin_ai_system_prompt_ids` migration, run before the seed, fixes this once:

1. In one transaction, for each prompt with an id from 6 to 49 that is not a built-in constant: insert a copy (same title, description, content, timestamps) at the next id ≥ 50, repoint `ai_systems.system_prompt_id` from the old id to the new one, then delete the old row. Copy-then-repoint-then-delete is required because the only foreign key (`ai_systems.system_prompt_id`, `ON DELETE SET NULL`, `ON UPDATE NO ACTION`) neither cascades an id change nor tolerates the old row being deleted first — that would silently null the system's prompt. `ai_systems.system_prompt_id` is the only column in the schema that refers to a prompt id.
2. Set the table's `AUTO_INCREMENT` to `max(50, MAX(id) + 1)`. MySQL never lowers it below `MAX(id) + 1`, so this is safe to run on any data. (`ALTER TABLE` commits implicitly, so it runs after the transaction, as a separate statement.)
3. Log each moved prompt as `old id → new id`, so the deploy output records what changed.

`down()` is a no-op: the move is harmless to keep, and undoing it would reopen the collision.

The seed migration then inserts id 6. It still refuses to overwrite — it throws, naming the row's title, if id 6 is somehow taken — but after the reservation step that can only mean a built-in already lives there. Future built-ins take 7, 8, … up to 49 with no production check needed. The admin's prompts keep their content and their AI system assignments; only their ids change, and nothing outside `ai_systems` refers to those ids (prompts are chosen in the UI by title).

code-talker's `AiSystemPrompt::scopeOrdered()` pins ids 1–5 to the top and sorts the rest by title; id 6 therefore sorts among the admin's own prompts. Changing that needs a code-talker change and is left out.

"AI systems that use the Company Intel prompt" = active `chat`-role `AiSystem`s with `system_prompt_id = 6`. The default system, if any, is used regardless of its own prompt; the session instructions always use the Company Intel prompt's content (Decision 5), so a default with a different assigned prompt still behaves correctly.

### 4. `company-intel` feature key

Add it to both form requests' `in:` rules and to `ALL_FEATURES` in the AI system form, with a label. `TargetedResumeService::createConversation()` composes its system prompt from the `targeted-resume`/`cover-letter` defaults only, so a system that is also the `company-intel` default is unaffected there.

### 5. One turn runner, two drivers

`CompanyIntelService` owns the lifecycle:

- `start(Application, AiSystem, mode)` — in one transaction (application row locked): refuse if Role intel exists (409); create the `AiConversation` (`feature = company-intel`, title "Intel: {position} at {company}"), its system message and first user message, and the `RoleIntel` (`queued`). Then, after commit: `background` dispatches `RunCompanyIntelJob`; `stream` leaves `context.auto_start_pending = true` for the intel page to start, exactly as the analysis chat does.
- `runTurn(RoleIntel, ?string $userMessage): Generator` — the single turn implementation (history rebuild, agent with tools, `AiLlmMessage` logging, assistant message, usage), yielding SSE frames. The streamed endpoint relays them; the job drains the generator and discards the frames. Sets `running` before and `ready`/`failed` after.

*Alternative considered:* reuse `AiOperatorRunner` — rejected: it creates a fresh single-turn conversation per run, has no host tool registry and no follow-up turns. *Alternative:* generalize `TargetedResumeService::continueConversation()` — rejected for this change: it is ~280 lines entangled with targeted-resume metadata syncing and page-reload signals; extracting a shared runner is worthwhile but is a separate refactor. The intel runner is written fresh against the same structure, kept smaller (no max-token continuation loop beyond what the agent's own `maxSteps` covers, no metadata sync).

Instructions = Company Intel prompt content + `\n\n---\n\n` + a host-owned "Recording your findings" section (a Blade/Markdown resource) that names the tools and the rules (record the company before insights; one insight per finding; cite sources; never attach a rejected company). Keeping the tool contract out of the user-editable prompt means editing the prompt cannot break recording.

### 6. Tools

`CompanyIntelToolRegistry` (shaped like `TargetedResumeToolRegistry`) discovers `app/Services/Mcp/Tools/CompanyIntel/`:

| Tool | Does |
| --- | --- |
| `get-intel-context` | job title, company name, location, description; the attached Company and its insights if any; the rejected companies |
| `save-company-profile` | find-or-create the Company by domain, else by name; refuse a rejected name/domain; update metadata (only fields supplied); attach to the Role intel |
| `add-company-insight` | add an insight to the attached Company (refuses when none is attached) |
| `save-role-intel` | update the supplied Role intel fields |

Every tool resolves its Role intel from the conversation (`role_intels.ai_conversation_id`) and refuses with a model-readable message when the conversation is not an intel session — the tree-wide discovery in `AppServiceProvider` makes them grantable to chat bots, and this makes such a grant inert.

The agent also gets the AI system's granted tools: `new ChatBotToolRegistry($conversation, $system->allowed_tools ?? [])->toLaravelAiTools()`, merged after the intel tools with intel names winning a collision. This is how `fetch-web-page` and friends (and their domain policies) apply, with no new grant mechanism.

### 7. Automatic start hooks into `addStatusUpdate`

`ApplicationService::addStatusUpdate()` is the single place an `applied` entry is created (`markApplied`, `createApplied` and the history form all go through it). When the new entry's status is `Applied`, it registers `DB::afterCommit(fn () => $companyIntel->autoStart($application))`. `autoStart` does nothing without a default or when Role intel exists, and catches and logs every exception — the status change has already committed and must not be reported as failed. *Alternative:* a model observer on `ApplicationStatusUpdate` — rejected: it would also fire inside the backfill migration and test factories.

### 8. The job

`RunCompanyIntelJob` (`ShouldQueue`, `tries = 1`, `timeout = 280`, `failOnTimeout = true`) holds the Role intel id, calls `runTurn()` and drains it. `failed()` marks the Role intel `failed` with a short reason. One try because a retried agent turn would duplicate tool writes; the Retry button is the deliberate re-run. 280 s keeps under the worker's 299 s so the job's own timeout wins and `failed()` runs.

Retry (`POST …/retry`) on a `failed` Role intel re-runs the pending turn: background from the list, streamed from the intel page. A retry removes nothing — the last user message is still the pending prompt, as `continueConversation(null)` already assumes.

### 9. The "already applied" first message

`TargetedResumeService::buildInitialAnalysisMessage()` gains a variant: when the application's status `isPipeline()`, the opening line is "I have already applied to this. Please provide a fit score, but there is no need for a targeted resume or cover letter." `createApplied()` takes an optional `AiSystem`; when given, it calls `startConversation()` inside the same transaction after adding the `applied` entry, so the variant applies. `beginAnalysis()` gets it for free since it reads the application's status. The `intent=applied` request accepts an optional `ai_system_id` validated like the analyze path; the separate-models guard does not apply to it (no documents are produced).

### 10. Wrong company

`POST …/role-intel/{roleIntel}/wrong-company` with either `company_id` (attach existing) or `hint` (required otherwise, ≤ 500 chars). In one transaction: append `{name, domain}` of the current Company to `rejected_companies`, null `company_id`, delete the old Company when no other Role intel refers to it (insights cascade). Then:
- `company_id` → attach it; no turn.
- `hint` → add a user message "The company you identified ({name}) is wrong. The correct company is: {hint}. Please research the correct company and record it." and respond so the page streams the turn (same mechanism as `auto_start_pending`).

The company picker lists existing Companies by name excluding the rejected ones.

### 11. Routes

Pages (`routes/admin-employment.php`, `auth` + `can:edit-resume` + Inertia), names `admin.employment.*`:
- `GET /admin/employment` lives in `routes/admin.php` (`/admin` group) since it is a hub.
- `GET /admin/employment/company-intel` — list
- `GET /admin/employment/company-intel/{roleIntel}` — intel page

JSON (`routes/api-web.php`, prefix `/api/admin/employment/company-intel`, `edit-resume`):
- `POST applications/{application}` — start (`ai_system_id` optional, `mode=background|stream`); 409 when intel exists; 422 when no eligible system is given and there is no default
- `GET eligible-systems` — the chooser's list and whether a default exists
- `POST {roleIntel}/chat` — SSE turn (with `message`, or none to run the pending one)
- `GET {roleIntel}/state` — status, company, insights, role fields (polled while `queued`/`running`)
- `POST {roleIntel}/retry`, `POST {roleIntel}/wrong-company`

Domain refusals are `ApplicationException` subclasses (or a sibling `CompanyIntelException` registered with the same renderer) so 404/409/422 mapping stays in one place.

### 12. Applications list payload

`ApplicationController::index` eager-loads `roleIntel:id,application_id,status,company_id`. For rows without Role intel, one extra query maps lower-cased company names to the newest Role intel id whose Company has that name (only for names on the current page), giving `intel: {id, status, scope: "role"|"company"} | null`. No N+1.

### 13. UI design

Restraint governs: every new surface is built from the admin's existing MUI pieces (PageHeader, DataTable, Card, StatusChip, ResponsiveButton, ConfirmDialog, the applications ChatPanel), no gradients, no shadows, no new palette.

- **Hubs** — `NavGrid`, unchanged. Icons: Employment `WorkOutline`, Blog `Feed`, Company Intel `TravelExplore`.
- **List Intel column** — a single icon button (`TravelExplore`) linking to the intel page; filled primary for role intel, outlined/secondary with tooltip "Company intel from {position}" for company-only; a small `CircularProgress` (16 px) replaces the icon while `queued`/`running`; error-coloured icon when `failed`. Em dash otherwise, matching the Resume column.
- **Actions column** — order Pass, Begin intel (`PersonSearch`, distinct from the column's `TravelExplore` link icon), Open, Delete. Conditional icons sit left, so Open and Delete keep their positions across rows; the column reads as a right-aligned stack.
- **Discussion header** — `ResponsiveButton` "Intel" (`TravelExplore`), outlined primary, placed after Pass and before Job URL. While starting, label "Starting…".
- **System chooser** — a `Dialog` titled "Choose an AI system for intel", a radio list of eligible systems (name and model), primary button "Begin intel". Empty state: text "No AI system uses the Company Intel prompt yet. Assign it to a system in AI Systems." with a link.
- **Company Intel list** — `PageHeader` "Company Intel" + DataTable: Company (bold; "Unidentified" in secondary text), Position (link to Discussion), State (`StatusChip`), AI system, Last activity, Open. Empty state: "Intel is started from an application." with a link to Applications.
- **Intel page** — the Discussion page's frame (sticky header with back arrow, title "{position} at {company}"), two tabs as on the Discussion page: **Chat** and **Findings**. Findings is a two-column layout on desktop (single column on phones): left Company card — name, website link, a definition list of metadata with "Unknown" in secondary text, summary, then insights grouped by category, each as title + Markdown + source link; right Role card — summary then the Markdown sections in a fixed order, empty ones omitted. Header actions: Application (back to Discussion), Wrong company (outlined warning, only when a Company is attached), Retry (only when `failed`).
- **Wrong company dialog** — two choices as radios: "Research again with a hint" (text field, placeholder "Correct company name or website") and "Choose an existing company" (Autocomplete). Primary button "Detach and research" / "Detach and attach". Body copy states the consequence: "{name} will be detached from this role and removed if no other application uses it."

The frontend-design pass happens at implementation: render each surface at desktop and phone width, critique, and send screenshots before merge.

## Risks / Trade-offs

- [An intel turn with web tools can exceed 280 s] → `maxSteps` bounded (8) and the web fetcher's own caps; a timeout marks `failed` with "Research timed out" and Retry is one click. Measured on the first real runs; the bound is a constant to tune.
- [Duplicate companies when the agent omits the website] → domain matching first, case-insensitive name second; the tool description insists on the website. Merging is out of scope.
- [The queue worker runs old code after deploy] → already a documented deploy step (`supervisorctl restart`); repeated in the migration plan.
- [Auto-start runs a paid model without the admin watching] → only when the admin has deliberately set a `company-intel` default; usage is recorded on the conversation like any other.
- [Deleting a rejected Company loses insights another role might later want] → only when nothing references it; this is the user's stated rule.
- [Intel tools grantable to chat bots by tree-wide discovery] → each tool refuses outside an intel session.
- [Admin-made prompts already sit in 6–49 in production] → moved to ≥ 50 with their AI system links repointed, in one transaction; the seed still refuses to overwrite as a last guard.
- [Something outside the database remembers a prompt id (a bookmark to an edit page)] → accepted; prompts are chosen by title everywhere in the UI.
- [Hard-coded feature-key lists in two requests and one TSX file] → kept as is for consistency; the change touches all three and a test asserts `company-intel` is accepted.

## Migration Plan

1. Migrations: `reserve_builtin_ai_system_prompt_ids`, `create_companies_table`, `create_company_insights_table`, `create_role_intels_table`, `seed_company_intel_prompt` (data; inserts prompt id 6, throws if id 6 is taken; `down()` deletes id 6). Run against `jasonvertucio` and `wink` locally.
   - `reserve_builtin_ai_system_prompt_ids` runs first and moves any admin-made prompt in ids 6–49 to ≥ 50 (repointing AI systems), then raises the auto-increment to at least 50. Before deploying (developer-owned), it is worth a look at what will move: `SELECT id, title FROM ai_system_prompts WHERE id BETWEEN 6 AND 49`, and afterwards confirm each affected AI system still shows its prompt.
2. `composer dump-autoload` (new factories in the classmap).
3. Deploy (developer-owned): `composer install`, `npm ci && npm run build`, `php artisan migrate`, `php artisan optimize:clear`, `sudo supervisorctl restart <worker>`.
4. After deploy (developer-owned): fill in the Company Intel prompt, assign it to a system with web tools granted, optionally make it the `company-intel` default.

Rollback: revert the code and roll back the four schema/seed migrations (the id reservation stays); no existing table is altered, so rollback loses only intel data.

## Implementation notes

Where the build departed from the decisions above:

- One migration, `create_company_intel_tables`, creates all three intel tables; `ai_feature_prompts` was dropped in favour of fixed id 6 (Decision 3). `role_intels.application_id` cascades on a hard delete.
- The recording instructions are a PHP method (`CompanyIntelService::recordingInstructions()`) like the other built-in prompt text, not a resource file.
- A wrong-company hint is not stored by `rejectCompany()`: the endpoint returns the correction message and the intel page sends it as the next chat turn, so the admin watches it run. The spec's "a message carrying the hint is added to the session" holds through the chat endpoint.
- Turns emit `page_reload` after every tool result and at the end, so the Findings tab refreshes while research runs; no new SSE event type was added.
- "I Applied" offers a "Ask {system} for a fit score" checkbox (on by default) in the confirmation dialog, because the AI-system selector has no empty choice and is preselected with the targeted-resume default.
- The AI system form's display-only prompt lock maps `company-intel` to prompt 6, like `targeted-resume` → 4 and `cover-letter` → 5.
- Intel tools check `hasPermissionTo('edit-resume')`, not `can()`, because Keystone registers no gates in the queue worker.
