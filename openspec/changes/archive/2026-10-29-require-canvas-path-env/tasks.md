## 1. Configuration

- [x] 1.1 Change `config/canvas.php` to `'path' => env('CANVAS_PATH')` and verify `CANVAS_PATH=canvas php artisan config:show canvas.path` prints `canvas`
- [ ] 1.2 Add `CANVAS_PATH` to `.env.production` (value `canvas` unless the developer chooses otherwise) and confirm with `grep CANVAS_PATH .env.production`
- [x] 1.3 Confirm `.env.example` documents `CANVAS_PATH` and that `phpunit.xml` or the test env supplies it; verify `php artisan test --compact --filter=AdminHubsTest` and `--filter=PostVisibilityTest` pass

- [x] 1.4 Replace `/canvas` in `resources/js/admin/navigation.json` with `{canvas.path}` and resolve it in `AdminNavigationService::load()`; verify with a feature test that the Blog hub's Blog Admin tile href equals `config('canvas.path')` with a leading slash, for a path set with and without one
- [x] 1.5 `.env.example` uses `CANVAS_PATH=/admin/blog/canvas`; verify with `grep CANVAS_PATH .env.example`

- [x] 1.6 Replace the remaining hardcoded `/canvas` with the configured path: `resources/config/config.json` (`{canvas.path}`, filled by `App\Support\CanvasPath` in `TopNavbar`, `HandleInertiaRequests` and `HomeController`) and the Shift+B shortcut in `resources/js/home.js` (reads `data-canvas-path` from `home.blade.php`); verify with `tests/Unit/CanvasPathTest.php` and a repo-wide grep for `/canvas`
- [x] 1.7 Ran the full suite: 1576 tests, the same 31 errors and 23 failures as before this change (none new)

## 2. Docs

- [x] 2.1 Update the Canvas path note in `.claude/context/media-and-blog.md` (currently "`/canvas` path (package default)") to say it comes from `CANVAS_PATH` with no default; verify by reading the file
