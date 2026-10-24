Tests use `DatabaseTransactions` (never `RefreshDatabase`). Run new migrations against both `jasonvertucio` and `wink` (`DB_DATABASE=wink php artisan migrate`). Every UI task ends with a frontend-design pass: screenshots at desktop and phone width, critiqued and fixed, then sent to the user before merge.

## 1. Hubs (navigation only — can ship on its own)

- [x] 1.1 Edit `resources/js/admin/navigation.json`: add the `/admin/employment` entry directly after the dashboard (items Applications, Application Metrics, Resume Management, Cover Letter Management, Company Intel) and a `/admin/blog` entry (`can: moderate-comments`; items Blog Admin with `can: manage-blog` + `external: true`, Comments); replace the six dashboard items with Employment first and Blog last. Verify with a feature test on `AdminNavigationService::getNavBlocksForRoute('/admin', …)` asserting order and the absent tiles
- [x] 1.2 Add `AdminController::employmentHub()` (route in the `/admin` group) and `blogHub()` (route in the `moderate-comments` group) with pages `employment/Hub.tsx` and `blog/Hub.tsx` modelled on `resume/Hub.tsx`. Verify with feature tests: tile order for an `edit-resume` user, Comments-only for a `manage-comments` user, 403 for a user with neither blog permission, login redirect for guests
- [x] 1.3 Point back links at the hubs: Resume Management, Cover Letter Management, Applications and Application Metrics → `/admin/employment` ("Back to Employment"); Comments → `/admin/blog` ("Back to Blog"). Verify by grepping `backHref` in those five pages
- [x] 1.4 Feature test that the app bar lists Employment as the first area after the dashboard for an `edit-resume` user, then a design pass on both hubs and the dashboard (screenshots)

## 2. Intel data model

- [x] 2.1 Create migrations for `companies`, `company_insights` and `role_intels` per design §2; run them against both databases and verify with `php artisan migrate:status` on each
- [x] 2.2 Create enums `RoleIntelStatus` and `CompanyInsightCategory`, models `Company`, `CompanyInsight`, `RoleIntel` (casts, relations) and `Application::roleIntel()`; add factories and run `composer dump-autoload`. Verify with a unit test that a `RoleIntel` factory builds with its application and company
- [x] 2.3 Add `Company::normalizeDomain()` and a find-or-match helper (domain first, case-insensitive name second). Verify with unit tests: `https://www.Acme.com/careers` → `acme.com`; name match ignores case; domain match beats a name match
- [x] 2.4 Write the `reserve_builtin_ai_system_prompt_ids` migration per design §3 (move admin-made prompts in 6–49 to ≥ 50 by copy → repoint `ai_systems.system_prompt_id` → delete, in one transaction; then raise `AUTO_INCREMENT` to `max(50, MAX(id)+1)`; log each move; no-op `down()`). Verify with tests that stage prompts at 7 and 8 with an AI system on 8, run the migration's logic, and assert: both moved to ≥ 50 with identical content, the AI system points at the moved 8, ids 1–5 untouched, and a newly created prompt gets an id ≥ 50. Run it against both databases and confirm locally that AI system 11 still shows "Bonsai…"
- [x] 2.5 Add `PROMPT_ID_COMPANY_INTEL = 6` and `buildCompanyIntelPrompt()` (starter content), and write the `seed_company_intel_prompt` data migration in the shape of `2026_05_27_150002_seed_default_ai_system_prompts` (insert id 6; throw naming the existing title if id 6 is taken; `down()` deletes id 6). Verify with tests that the prompt is found by id after renaming its title, and that the migration's guard throws when id 6 exists

## 3. Feature default and eligible systems

- [x] 3.1 Add `company-intel` to the `in:` rules of `StoreAiSystemRequest` and `UpdateAiSystemRequest` and to `ALL_FEATURES` (with a label) in `ai/systems/Form.tsx`. Verify with feature tests: saving with `company-intel` makes that system the only default; an unknown key fails validation
- [x] 3.2 Implement eligible-system lookup (active chat-role systems assigned the Company Intel prompt) and the default lookup. Verify with unit tests covering inactive systems, other prompts and no prompt row

## 4. Intel tools

- [x] 4.1 Create `CompanyIntelToolRegistry` modelled on `TargetedResumeToolRegistry`, discovering `app/Services/Mcp/Tools/CompanyIntel/`, plus the merge with `ChatBotToolRegistry($conversation, $system->allowed_tools)` (intel names win). Verify with a test that a system without `fetch-web-page` granted is not offered it, and one with it is
- [x] 4.2 Implement `get-intel-context`, `save-company-profile`, `add-company-insight`, `save-role-intel` with each tool refusing outside an intel session. Verify with feature tests per tool, including: second application at the same domain reuses the Company; insight refused with no Company attached; partial role update leaves other fields intact; a tool called from a non-intel conversation refuses
- [x] 4.3 Make `save-company-profile` refuse a name or domain listed in the Role intel's `rejected_companies` with a model-readable message. Verify with a feature test
- [x] 4.4 Write the host "Recording your findings" instructions resource and the instruction composer (prompt content + separator + resource). Verify with a unit test that the composed instructions contain both parts

## 5. Intel service, job and auto-start

- [x] 5.1 Implement `CompanyIntelService::start()` (lock, 409 when intel exists, conversation + system message + first user message + `queued` Role intel, then dispatch or set `auto_start_pending`). Verify with feature tests: the first message text and job details; 409 on a second start; `background` dispatches the job (`Queue::fake()`); `stream` dispatches nothing
- [x] 5.2 Implement `CompanyIntelService::runTurn()` (history, agent, tools, `AiLlmMessage` logging, assistant message, usage, `running` → `ready`/`failed`). Verify with a feature test using a faked agent/provider that a turn stores the assistant message and leaves the Role intel `ready`, and that an exception leaves it `failed` with a reason
- [x] 5.3 Create `RunCompanyIntelJob` (`tries = 1`, `timeout = 280`, `failOnTimeout`, `failed()` marks the Role intel). Verify with a test that a job failure marks `failed`
- [x] 5.4 Hook `ApplicationService::addStatusUpdate()` to call `autoStart()` after commit for `applied` entries; `autoStart()` no-ops without a default or with existing intel and swallows/logs errors. Verify with feature tests: Mark Applied with a default queues the job; without a default creates nothing; with existing intel creates nothing; I Applied on the New Session page queues it; an exception inside `autoStart` leaves the application `applied` and the response successful

## 6. "Already applied" first message

- [x] 6.1 Add the fit-score-only variant to `TargetedResumeService::buildInitialAnalysisMessage()` chosen when the application's status `isPipeline()`. Verify with tests: Begin Analysis on a `draft` application uses the original opening; on an `applied` one uses the new opening
- [x] 6.2 Let `ApplicationService::createApplied()` take an optional `AiSystem` and start the session in the same transaction; accept optional `ai_system_id` on `intent=applied` in the store request and controller. Verify with feature tests: with a system → session attached with the new opening and `auto_start_pending`; without → no session, as before
- [x] 6.3 Update `Create.tsx` so "I Applied" sends the selected `ai_system_id` when one is selected, and its confirmation dialog says a fit-score session will start. Verify by recording a job both ways in the browser and checking the Discussion page

## 7. Intel endpoints and pages

- [x] 7.1 Add routes (`routes/admin-employment.php` pages, `api-web.php` JSON) and controllers per design §11, with domain refusals mapped through the existing exception renderer. Verify with feature tests: 403 without `edit-resume`, login redirect for guests, 409 duplicate start, 422 ineligible `ai_system_id`, 422 no default and no system given
- [x] 7.2 Implement the SSE `chat` endpoint relaying `runTurn()` with the same framing as the applications chat. Verify with a feature test asserting frames and the stored assistant message
- [x] 7.3 Implement `state`, `retry` and `eligible-systems`. Verify with feature tests: `state` returns company, insights and role fields; retry on `failed` re-runs (background dispatch) and is refused unless `failed`
- [x] 7.4 Implement `wrong-company` (hint or `company_id`; reject + detach + delete-if-unreferenced in one transaction; hint appends the user message). Verify with feature tests: unreferenced Company and insights deleted; shared Company kept; `company_id` attaches without a turn; hint message text; a rejected Company is excluded from the picker list
- [x] 7.5 Build the Company Intel list page (`employment/company-intel/Index.tsx`) per design §13 with its empty state. Verify with a feature test on the Inertia props (deleted applications excluded, newest activity first), then design pass + screenshots
- [ ] 7.6 Build the intel page (`employment/company-intel/Show.tsx`): Chat tab reusing the applications chat components with auto-start, Findings tab with Company and Role cards, polling `state` while `queued`/`running`, Retry and Wrong company actions and dialog. Verify in the browser: a background run updates without reload; a streamed start begins on open; wrong company with a hint streams a new turn; then design pass + screenshots at desktop and phone width
- [x] 7.7 Build the system chooser dialog (shared by list and Discussion page) with its no-eligible-system state. Verify in the browser with no default and with zero eligible systems, then screenshots

## 8. Applications list and Discussion page

- [x] 8.1 Add the `intel` payload to `ApplicationController::index` (eager-loaded Role intel plus one company-name lookup for the page). Verify with a feature test covering role, company-only and none, and a query-count assertion that the list does not N+1
- [x] 8.2 Add the Intel column and reorder the actions column to Pass, Begin intel, Open, Delete in `applications/Index.tsx`; Begin intel starts in the background or opens the chooser. Verify in the browser on draft/applied rows with and without intel, then screenshots
- [ ] 8.3 Pass Role intel to `applications/Show.tsx` and add the Intel header item (open existing, or start with `mode=stream` / chooser, then visit the intel page). Verify in the browser both ways, then screenshots

## 9. Documentation and wrap-up

- [x] 9.1 Update CLAUDE.md: Employment and Blog hubs, the `manage-blog` tile fix, a Company Intel section (model split, the Company Intel prompt as fixed id 6 alongside 1–5, ids 1–49 reserved for built-ins with admin prompts from 50, auto-start rule, job timeout, tool refusal outside intel sessions, wrong-company rule), the new queued job in *Queues*, new models in the Models table. Verify by reading the sections against the design
- [x] 9.2 Run the affected suites (`php artisan test --compact` on the new and touched test files) and `npm run build`; confirm both pass, then ask the user whether to run the full suite
- [ ] 9.3 Send the collected screenshots of every new or changed surface to the user and wait for their reaction before merging to `develop`
