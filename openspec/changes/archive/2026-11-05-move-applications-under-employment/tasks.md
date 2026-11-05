## 1. Routes

- [x] 1.1 Move the Applications page routes (`index`, `create`, `show`, `destroy`, `parser.confirm`, `parser.reject`) and Application Metrics (`metrics`) from `routes/admin-resume.php` into `routes/admin-employment.php`, under `/admin/employment/...` and named `admin.employment.*`. Keep the `whereNumber` constraints and update the file's header comment. Verify `php artisan route:list --path=admin/employment` lists all seven, and that `--path=admin/resume` shows no `applications` or `metrics` routes.
- [x] 1.2 Repoint the three `targeted-builder` redirects in `routes/admin-resume.php` at `admin.employment.applications.{index,create,show}`. Add no other redirects. Verify with `php artisan route:list --path=admin/resume/targeted-builder`.
- [x] 1.3 In `routes/api-web.php`, move the `applications` group out of the `api/admin/resume` group into a new `api/admin/employment/applications` group (`auth`, `can:edit-resume`) named `admin.employment.applications.`, with the inner routes unchanged. Leave `editor` and `targeted-resume` where they are. Verify `php artisan route:list --path=api/admin/employment/applications` lists every endpoint, `--path=api/admin/resume` shows only `editor` and `targeted-resume`, and `php artisan route:list` reports no duplicate-name error.

## 2. Server-side references

- [x] 2.1 Replace `admin.resume.applications.*` with `admin.employment.applications.*` in `ApplicationController`, `ApplicationSessionController` and `TargetedResumeController`. Verify `grep -rn "admin\.resume\.\(applications\|metrics\)" app routes` returns nothing.

## 3. Components

- [x] 3.1 `git mv resources/js/admin/pages/resume/applications resources/js/admin/pages/employment/applications` and `git mv resources/js/admin/pages/resume/metrics resources/js/admin/pages/employment/metrics`. Change `useLatestGeneratedArtifacts.ts`'s `../targeted/tailoredResumeParser` imports to `@/admin/pages/resume/targeted/tailoredResumeParser`. Verify `git status` shows renames and that no relative `../` import in the moved folders points at a folder that no longer exists beside them.
- [x] 3.2 Update the `Inertia::render` names in `ApplicationController` (`employment/applications/{Index,Create,Show}`) and `ResumeMetricsController` (`employment/metrics/Index`). Verify `grep -rn "render('resume/\(applications\|metrics\)" app` returns nothing.

## 4. Frontend URLs

- [x] 4.1 Replace page URLs (`/admin/resume/applications` → `/admin/employment/applications`, `/admin/resume/metrics` → `/admin/employment/metrics`) and API URLs (`/api/admin/resume/applications` → `/api/admin/employment/applications`) in `navigation.json`, `navHelpers.ts` (doc comment), every file in the moved folders, `resume/targeted/{Index,Edit}.tsx`, `employment/company-intel/{Index,Show}.tsx`, `cover-letters/Create.tsx` and `ai/conversations/Show.tsx`. Verify `grep -rn "admin/resume/\(applications\|metrics\)" resources/js` returns nothing, and that `/api/admin/resume/editor` and `/api/admin/resume/targeted-resume` are untouched.
- [x] 4.2 Run the project's TypeScript check and ESLint/Prettier on the touched and moved frontend files, and fix anything they flag so the husky hook passes. Verify both commands exit clean for those files.

## 5. Tests

- [x] 5.1 Update every feature test that references the moved route names, URLs or component names: `admin.resume.applications.*`, `admin.resume.metrics`, `/admin/resume/applications`, `/admin/resume/metrics`, `/api/admin/resume/applications`, `resume/applications/*` and `resume/metrics/*`. The files are `ApplicationListTest`, `ApplicationCreateTest`, `ApplicationDiscussionTest`, `ApplicationPermissionTest`, `ApplicationHttpSmokeTest`, `ApplicationAccessHardeningTest`, `ApplicationFormRequestTest`, `ApplicationStatusUpdateTest`, `ApplicationAlreadyAppliedSessionTest`, `ApplicationMetricsHttpTest`, `ResumeMetricsTest`, `JobUrlParseTest`, `SessionSystemSwitchTest`, `TargetedResume{List,Systems,DownloadOnDemand}Test`, `AiConversationStatusTest`, `AiSystemVoiceRoleTest`, `CompanyIntel/{ApplicationIntelColumn,CompanyIntelLifecycle}Test` and `AdminHubsTest`. Verify `grep -rn "admin\.resume\.\(applications\|metrics\)\|admin/resume/\(applications\|metrics\)\|'resume/\(applications\|metrics\)/" tests` returns nothing except the 404 tests from 5.2, and that each touched file passes with `php artisan test --compact <file>`.
- [x] 5.2 Add tests (in `ApplicationHttpSmokeTest`, with `DatabaseTransactions`) that check four things. First, the old page URLs (`/admin/resume/applications`, `/new`, `/{id}`, `/admin/resume/metrics`) and an old API URL (`POST /api/admin/resume/applications`) answer 404 with no redirect for an `edit-resume` holder. Second, the `targeted-builder` URLs 301 to the new Applications URLs. Third, the new page URLs serve the moved components. Fourth, a user without `edit-resume` is refused at the new page and API URLs. Verify the file passes.
- [x] 5.3 Update `AdminHubsTest` so that the Employment hub's Applications and Application Metrics tile hrefs are the new URLs, and its "existing URLs still work" list drops `/admin/resume/applications` and `/admin/resume/metrics`. Verify the file passes.

## 6. Docs and verification

- [x] 6.1 Update `.claude/context/applications.md`: the route list (pages in `routes/admin-employment.php`, JSON under `/api/admin/employment/applications`, metrics at `/admin/employment/metrics`), the component folder paths and the `POST` endpoint in *Creating an application*. Verify `grep -n "admin/resume/\(applications\|metrics\)\|pages/resume/\(applications\|metrics\)" .claude/context/applications.md` returns nothing.
- [x] 6.2 Run `npm run build` and confirm it succeeds. Then, on the local site, open the Applications list, New Session, a Discussion page (send a chat message and add a status update) and Application Metrics. Confirm that the nav marks the right page as current and that the browser's network tab shows only `/api/admin/employment/applications` calls.
