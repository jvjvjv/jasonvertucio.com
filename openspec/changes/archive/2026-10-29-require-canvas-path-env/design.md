## Context

`config/canvas.php` line 16 is `env('CANVAS_PATH', 'canvas')`. Observed values: `.env` and `.env.example` set `/admin/blog`; `.env.production` sets none, so production currently uses the `canvas` fallback (`/canvas`, as the existing specs and `.claude/context/media-and-blog.md` describe). Views read `config('canvas.path')` for Edit/Stats links. Canvas documents a null path as "resides under the same path as the application".

`resources/js/admin/navigation.json` hardcodes `"href": "/canvas"` for Blog Admin and is read only by `AdminNavigationService::load()` (no JS imports it). The developer has set the local `CANVAS_PATH` to `/admin/blog/canvas` to avoid shadowing the Blog hub.

Canvas builds its SPA router basename as `'/'.config('canvas.path')` (`Canvas\Support\Paths::basePath()`) without trimming, so `CANVAS_PATH` must have **no leading and no trailing slash**: a leading slash yields `//…`, a trailing slash yields a basename the browser URL cannot match ("Router basename … is not able to match the URL"). The route prefix is trimmed by Laravel, so only the SPA breaks.

## Goals / Non-Goals

**Goals:**
- Remove the in-code default so the environment is the single source.
- Make every env file define the variable.

**Non-Goals:**
- Changing which path production uses, or the `/admin/blog` hub route.
- Touching `CANVAS_STORAGE_PATH`.

## Decisions

- **Drop the fallback rather than keep it.** This is the literal request. Alternative: keep `'canvas'` as the default; rejected as it is what hides a missing variable.
- **Placeholder in navigation.json, resolved in the service.** The JSON holds `{canvas.path}`; `load()` substitutes `'/'.ltrim(config('canvas.path'), '/')`. Alternative: hardcode `/admin/blog/canvas` in the JSON; rejected because it drifts from `CANVAS_PATH`.
- **Set `CANVAS_PATH=canvas` in `.env.production`** to preserve today's production URL (`/canvas`), since that is the effective value now. Production may adopt `/admin/blog/canvas` later; that is the developer's call.

## Risks / Trade-offs

- [Unset variable mounts Canvas at the app root] → define it in `.env.example`, `.env.production` and the test environment; verify with `config:show canvas.path` before deploy.
- [A `CANVAS_PATH` of `/admin/blog` would shadow the Blog hub] → `.env`, `.env.example` and `phpunit.xml` now use `admin/blog/canvas`; the `admin-hubs` delta forbids `/admin/blog`.
- [Config cached on the server] → `php artisan optimize:clear` after deploy (already part of the deploy steps).

## Migration Plan

1. Developer adds `CANVAS_PATH` to the production `.env` (developer-owned; never edited from here).
2. Deploy, then `php artisan optimize:clear`; confirm `config:show canvas.path`.
Rollback: restore the fallback in `config/canvas.php`.
