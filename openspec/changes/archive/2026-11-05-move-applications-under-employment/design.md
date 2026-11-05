## Context

The Applications and Application Metrics page routes are registered in `routes/admin-resume.php` inside the `admin/resume` group (`auth`, `can:edit-resume`, `HandleInertiaRequests`), named `admin.resume.applications.*` and `admin.resume.metrics`. `routes/admin-employment.php` already holds an identical middleware group for `admin/employment` (Company Intel). The JSON API is in `routes/api-web.php`, nested in an `api/admin/resume` group (`auth`, `can:edit-resume`) that also holds the resume editor save and the targeted-resume markdown update. Those two stay put.

The page components live in `resources/js/admin/pages/resume/{applications,metrics}/` and are resolved lazily by name (`resolvePageComponent` over a glob), so a folder move only changes the render names. Inside `applications/`, one relative import reaches a sibling folder: `useLatestGeneratedArtifacts.ts` imports `../targeted/tailoredResumeParser`. Every other cross-folder import already uses the `@/admin/pages/...` alias. Page and API URLs are hard-coded strings in the React files, since the project has no route helper.

## Goals / Non-Goals

**Goals:**
- Pages, API, route names and component folders for Applications and Application Metrics all say "employment", so URL, name and file path agree.

**Non-Goals:**
- Redirects from the old page or API URLs.
- Renaming controller classes (`ResumeMetricsController` keeps its name).
- Moving Targeted Resumes, the resume editor, or their API.

## Decisions

**Page routes go into `routes/admin-employment.php`.** That file's group already has the right prefix, name prefix and middleware. Moved names: `applications.{index,create,show,destroy,parser.confirm,parser.reject}` and `metrics`, all under `admin.employment.`. Constraints carry over unchanged (`whereNumber`).

**The API becomes its own `api/admin/employment/applications` group in `routes/api-web.php`,** named `admin.employment.applications.`, with the same `auth` + `can:edit-resume` middleware. It sits beside the existing `api/admin/employment/company-intel` group. The inner route definitions move verbatim. The page and API names now share the `admin.employment.applications.` prefix, and their leaves do not collide. Pages use `index`, `create`, `show`, `destroy` and `parser.{confirm,reject}`. The API uses `store`, `update`, `apply`, `pass`, `analysis`, `chat`, `finalize`, `finalize-cover-letter`, `system-switch.*`, `status-updates.*`, `parse-url` and `parser.reparse`. Confirm this with `php artisan route:list` after the move.

**No redirects for the old URLs.** The user chose this. The old paths simply stop matching and answer 404. The existing `targeted-builder` redirects are kept and repointed at the new route names. Those redirects already exist, and leaving them on the removed names would throw a missing-route error rather than give a clean 404.

**Components move with `git mv` of the whole folder**, which keeps file history. After the move, `useLatestGeneratedArtifacts.ts` changes its import to the alias form (`@/admin/pages/resume/targeted/tailoredResumeParser`), the same form the folder already uses for Company Intel. `Inertia::render` names become `employment/applications/*` and `employment/metrics/Index`.

**URL constants are replaced literally.** `APPLICATIONS_API` in `Create.tsx` and `ChatPanel.tsx`, `MODEL_API` in `company-intel/Show.tsx`, `METRICS_URL` in `MetricsFilterBar.tsx`, `LIST_URL` / `NEW_SESSION_URL` in `Index.tsx` and `targeted/Index.tsx`, and the inline template strings are all updated. `/api/admin/resume/editor` and `/api/admin/resume/targeted-resume/*` are not touched.

## Risks / Trade-offs

- [A missed hard-coded URL now 404s instead of bouncing through a redirect] → After the edits, `grep -rn "admin/resume/\(applications\|metrics\)" app resources routes` must return only the `targeted-builder` comment, if anything. Run `npm run build` and click through list, New Session, Discussion (chat, finalize, status update, pass) and Metrics locally.
- [Mechanical rename across ~20 test files misses or over-matches] → Replace the full strings (`admin.resume.applications.`, `admin.resume.metrics`, `/api/admin/resume/applications`, `/admin/resume/applications`, `/admin/resume/metrics`, `'resume/applications/`, `'resume/metrics/`), then grep for any remainder and run every touched test file.
- [Tabs left open across the deploy fail their API calls] → Accepted; a reload fixes it.

## Migration Plan

Deploy as usual. `npm run build` is required, because the moved page chunks and the new URLs ship together with the routes. There is no migration and no data change. Restarting the queue worker isn't needed for this change, but the normal deploy restart is harmless. Rollback is reverting the commit and rebuilding.
