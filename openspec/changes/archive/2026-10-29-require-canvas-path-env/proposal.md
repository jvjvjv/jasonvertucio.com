## Why

`config/canvas.php` reads the Canvas base path as `env('CANVAS_PATH', 'canvas')`. The hardcoded `'canvas'` fallback means a missing `CANVAS_PATH` silently succeeds: `.env.production` does not define it today, so production relies on the fallback while `.env` and `.env.example` set `/admin/blog`. The path should come from the environment alone, so every environment states it explicitly.

## What Changes

- `config/canvas.php`: `'path' => env('CANVAS_PATH')` with no fallback.
- `.env.production` and `.env.example` define `CANVAS_PATH` explicitly so a deploy never boots with an unset value.
- The Blog Admin nav tile in `resources/js/admin/navigation.json` stops hardcoding `/canvas`: the file holds a `{canvas.path}` placeholder and `AdminNavigationService` fills it from `config('canvas.path')`.
- `.env.example` now uses `CANVAS_PATH=/admin/blog/canvas` so Canvas does not shadow the Blog hub at `/admin/blog` (already applied).
- **BREAKING**: an environment with no `CANVAS_PATH` no longer falls back to `canvas`. Canvas treats a null path as the application root, so an unset variable would mount the Canvas admin at `/`. Every environment (including CI) must define it.

## Capabilities

### New Capabilities

### Modified Capabilities
- `canvas-v7-blog-platform-integration`: the Canvas admin base path is driven solely by `CANVAS_PATH`, with no built-in default.
- `admin-hubs`: the Blog hub's Blog Admin tile opens the configured Canvas path, which must not be `/admin/blog`.

## Impact

- `config/canvas.php`, `app/Services/AdminNavigationService.php`, `resources/js/admin/navigation.json`, `.env.example`, `.env.production` (untracked, developer-owned), and the developer's local `.env` (already set).
- Views that build edit/stats links from `config('canvas.path')` are unchanged.
- `phpunit.xml` / test environment must supply `CANVAS_PATH` if tests exercise Canvas routes.
- Deployment: production `.env` must gain `CANVAS_PATH` before this ships (developer-owned step).
