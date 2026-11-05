## Why

Applications and Application Metrics sit in the Employment hub, but they still live under `/admin/resume`, and so do their JSON API and page components. That makes them look like part of Resume Management. An application is about getting a job, not somewhere a resume gets sent, so everything about applications should sit under Employment. Company Intel is already there.

## What Changes

- **BREAKING** The Applications pages move from `/admin/resume/applications` to `/admin/employment/applications`: the list (`/`), New Session (`/new`), the Discussion page (`/{application}`), soft delete (`DELETE /{application}`), and the job-URL parser confirm/reject endpoints (`/parser/{parser}/confirm|reject`).
- **BREAKING** Application Metrics moves from `/admin/resume/metrics` to `/admin/employment/metrics`.
- **BREAKING** The Applications JSON API moves from `/api/admin/resume/applications/*` to `/api/admin/employment/applications/*`. This covers store, update, apply, pass, analysis, chat, finalize, finalize-cover-letter, system-switch, status-updates, parse-url, parser reparse, and the AI-system model-status/model-warmup endpoints.
- All of those routes are renamed from `admin.resume.applications.*` / `admin.resume.metrics` to `admin.employment.applications.*` / `admin.employment.metrics`. The page routes move from `routes/admin-resume.php` to `routes/admin-employment.php`.
- The Inertia page components move with `git mv`: `pages/resume/applications/` → `pages/employment/applications/`, and `pages/resume/metrics/` → `pages/employment/metrics/`. Render names change to match.
- No redirects. The old page URLs and old API URLs answer 404. The existing `/admin/resume/targeted-builder*` redirects stay, now pointing at the new Applications routes.
- Every link, back link, API call and server-side `redirect` payload uses the new URLs: navigation, hub tiles, Company Intel, Targeted Resumes, Cover Letter create, the AI conversation page, and the controllers.
- Not changed: Resume Management, the resume editor, Targeted Resumes and their API (`/api/admin/resume/targeted-resume/*`), share codes, permissions, page behavior, and controller class names.

## Capabilities

### New Capabilities

_None._

### Modified Capabilities

- `admin-hubs`: Applications and Application Metrics are served under `/admin/employment`, and their former `/admin/resume` URLs no longer serve them.
- `admin-shell`: the "page beneath a page" scenario uses the new Applications URL.

## Impact

- **Routes**: `routes/admin-resume.php`, `routes/admin-employment.php`, `routes/api-web.php`.
- **Controllers**: `ApplicationController`, `ApplicationSessionController`, `TargetedResumeController`, `ResumeMetricsController` (route names and render names).
- **Frontend**: `navigation.json`, `navHelpers.ts`, every file under the moved `applications/` and `metrics/` folders, `resume/targeted/{Index,Edit}.tsx`, `employment/company-intel/{Index,Show}.tsx`, `cover-letters/Create.tsx` and `ai/conversations/Show.tsx`. Needs `npm run build`.
- **Tests**: every feature test that names the moved routes, requests the moved URLs or asserts the moved component names.
- **Docs**: `.claude/context/applications.md`.
- **Users**: bookmarks to the old URLs stop working. A browser tab left open on an old page has to be reloaded after the deploy, because its API calls now get a 404. No data, migration or dependency change.
