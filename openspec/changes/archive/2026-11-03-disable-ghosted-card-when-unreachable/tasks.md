## 1. Server

- [x] 1.1 Create `feature/disable-ghosted-card-when-unreachable` from `develop` and verify with `git branch --show-current`
- [x] 1.2 Write failing tests in `tests/Feature/ApplicationMetricsTest.php` for `ghostRateApplicable` and `ghostRate`: from exactly `today - threshold` (not applicable, rate null), one day earlier (applicable, rate is a number, with a recent to date too), no from, only a to, thresholds of 14 and 60 against a 30-day start, an older empty period (applicable, rate null), and the first-day sliver (not applicable, while `outcomes` and `timeline` still report it as ghosted). Verify they fail with `vendor/bin/phpunit tests/Feature/ApplicationMetricsTest.php`
- [x] 1.3 In `ApplicationMetricsService`, add the private `ghostRateApplicable()` check from `design.md`, return `ghostRateApplicable` from `build()`, make `kpis.ghostRate` null when it is false, update the `build()` PHPDoc shape, and verify the 1.2 tests pass
- [x] 1.4 In `tests/Feature/ApplicationMetricsHttpTest.php`, add `ghostRateApplicable` to the `metricsAt()` key list and assert it through the route for each preset at the 30-day threshold (`30d` false; `90d`, `ytd` and `all` true), and for a custom `from` at the boundary. Verify with `vendor/bin/phpunit tests/Feature/ApplicationMetricsHttpTest.php`

## 2. Page

- [x] 2.1 Add `ghostRateApplicable` to `PERIOD_PROPS` in `MetricsFilterBar.tsx` and to `MetricsProps` in `metrics/Index.tsx`, and verify with `npx tsc --noEmit`
- [x] 2.2 Add the optional `disabledReason` prop to `StatCard` per `design.md` (`aria-disabled`, `text.disabled` value and label, `caption` reason), pass it to the Ghosted card when `ghostRateApplicable` is false, and verify `npx eslint` passes on both files
- [x] 2.3 Run Prettier on the changed files under `resources/js` and verify `npm run build` succeeds

## 3. Docs

- [x] 3.1 Note the rule in the "Ghosted" entry of `.claude/context/applications.md`, and verify the entry names `ghostRateApplicable`

## 4. Verification

- [x] 4.1 Run `vendor/bin/phpunit tests/Feature/ApplicationMetricsTest.php tests/Feature/ApplicationMetricsHttpTest.php tests/Feature/ResumeMetricsTest.php` on host PHP (tests use the `wink` database), and verify all pass
- [x] 4.2 In a browser, switch All time → Last 30 days → Last 90 days → Last 30 days, then set a custom from date on both sides of the boundary, and verify the card greys and recovers each time without a reload (this is the only check on `PERIOD_PROPS`)
- [x] 4.3 Do a `frontend-design` pass on the disabled card, screenshot the KPI row enabled and disabled at 1280 and 360 pixels, and send them to the developer for a reaction before merging to `develop`
