## 1. Setup and baselines

- [x] 1.1 Create `feature/add-blog-breadcrumbs-and-admin-shell` from `develop` without touching the developer's uncommitted edits, and verify `git status` still shows `resources/css/blog.css` and `resources/views/blog/list.blade.php` as modified
- [x] 1.2 Confirm `phpunit.xml` points tests at the `wink` database and that the new tests will use `DatabaseTransactions`, and verify by reading the config and recording the host command (`vendor/bin/phpunit --filter=...`)
- [x] 1.3 Capture "before" screenshots of a chat page and of the admin Dashboard, Applications list and an application's Discussion page with the Playwright in the npx cache, saved to the session scratchpad, and verify the files exist

## 2. Blog breadcrumbs

- [x] 2.1 Write `tests/Feature/Blog/BreadcrumbsTest.php` (made with `php artisan make:test --phpunit`, using the `PostVisibilityTest` helpers as the pattern) covering every `blog-breadcrumbs` scenario that a response can show: each trail, both short addresses, empty listings, author preview, 404, feed, link versus current-page markup, and an escaped `<script>` title. Verify the new tests fail
- [x] 2.2 Add the anonymous `resources/views/components/breadcrumbs.blade.php` component (navigation landmark labelled "Breadcrumb", ordered list, `aria-current="page"` on the last entry, separators hidden from assistive technology, visible focus, wrapping text, no box shadow or gradient), and verify with the markup tests from 2.1 once 2.3 and 2.4 are done
- [x] 2.3 Add a private trail helper to `BlogController`, using named routes, and pass `breadcrumbs` from `index`, `topics`, `tags`, `topicList`, `tagList` and `post`, and verify `topicsOrTags()` needs no change by running the short-address tests
- [x] 2.4 Render `<x-breadcrumbs>` at the top of `main` in `blog/list`, `blog/topics`, `blog/tags` and `blog/single`, outside the populated/empty branch and preserving the developer's Edit link edit, and verify `tests/Feature/Blog/BreadcrumbsTest.php` and `PostVisibilityTest.php` pass
- [x] 2.5 Do a `/frontend-design` pass on the trail within the site's existing look, then screenshot `/blog`, a topic listing and a long-titled post at 1280 and 360 pixels, and verify from the screenshots and `scrollWidth` that the trail wraps with no horizontal scroll
- [x] 2.6 Commit the breadcrumb work on its own, staging only the breadcrumb hunks of `list.blade.php`, and verify `git diff` still shows the developer's Edit link and `blog.css` edits as uncommitted

## 3. Tokens and chat separation

- [ ] 3.1 Move the current `resources/js/admin/theme.ts` unchanged to `resources/js/chat/theme.ts`, point `chat/app.tsx` at it, and verify with `git diff --stat -M` (a pure rename) and a clean `npm run build`
- [ ] 3.2 Create `resources/js/admin/theme/tokens.ts`, `components.ts` and `index.ts` from the starting design plan, still exporting `theme` so `admin/app.tsx` is unchanged, with borders in place of shadows and no gradients, and verify with `npx tsc --noEmit` and `npm run build`
- [ ] 3.3 Replace the hard-coded colour in `pages/ai/conversations/Show.tsx` with a token, and verify `grep` finds no hex colours under `resources/js/admin` outside `theme/tokens.ts` and `utils/markdownSx.ts`

## 4. New shell

- [ ] 4.1 Add `buildNavAreas()` and `currentDestination()` to `layouts/admin-layout/navHelpers.ts` as pure functions, treating `/admin` as an exact match, and verify by checking the `admin-shell` address scenarios (including `/admin/resume/applications/5` and `/admin/resume/editor`) in the browser in 4.4
- [ ] 4.2 Build the sidebar and narrow-screen drawer with a labelled menu button, `aria-current` on the current destination, the log out control and no expand state, and verify keyboard use and Escape returning focus to the menu button in the browser
- [ ] 4.3 Rebuild `AdminLayout` with its exact props and move its flash logic unchanged, delete `DesktopNavItem.tsx` and `MobileNavSection.tsx`, make the drawer respect reduced motion, and verify with `npx tsc --noEmit` that no page file needed editing
- [ ] 4.4 Rebuild `NavGrid` and `PageHeader` behind their current props, with `DataTable` and `Pagination` following if the Applications list needs them for the review, and verify the Dashboard, the Employment hub, the Applications list and each 4.1 address in the browser as users with full and with narrow permissions

## 5. Review gate

- [ ] 5.1 Screenshot the Dashboard, Employment hub and Applications list at 1280 and 360 pixels, critique them against the design plan, the project's no gradient and no box shadow rules and the existing identity, fix what falls short, and verify by re-taking the screenshots
- [ ] 5.2 Build in the main checkout so `jasonvertucio.local` shows the work, send the developer the screenshots with `SendUserFile`, then **stop and wait for the developer's reaction**. This task is complete only when the developer has responded and any changes they asked for are made

## 6. Remaining shared components and theme coverage

- [ ] 6.1 Finish `DataTable`, `Pagination`, `EmptyTableRow`, `StatusChip` and `ConfirmDialog` behind their current props, and verify with `npx tsc --noEmit` and the design reference once 7.2 exists
- [ ] 6.2 Add theme overrides for the MUI components the pages rely on (Button, TextField, Select, Card, Table, Chip, Dialog, Menu, Tabs, Alert, Tooltip, date pickers), with visible focus throughout, and verify each one on the design reference
- [ ] 6.3 Screenshot the broader page set (AI system form, Resume Editor, Metrics charts, Comments, Roles dialog, Cover Letter Preview, a date picker and an application's Discussion page) and fix regressions in the theme first, and verify by re-taking the screenshots at 1280 and 360 pixels

## 7. Design reference

- [ ] 7.1 Write `tests/Feature/Admin/DesignReferenceTest.php` for the permitted user, 403, anonymous redirect, not-linked (no dashboard tile or `adminNav` entry) and no server records in the page's props, and verify the tests fail
- [ ] 7.2 Add `AdminController::designReference`, the route inside the `/admin` group with `can:edit-settings`, and `resources/js/admin/pages/DesignReference.tsx` showing tokens and component states from sample data held in the page file, and verify the 7.1 tests pass and the page renders in the browser
- [ ] 7.3 Add a test to `tests/Feature/AdminHubsTest.php` pinning that the design reference does not change the dashboard's tiles, keep the existing app-bar order tests unchanged, and verify the file passes

## 8. Guardrails and documentation

- [ ] 8.1 Add ESLint `no-restricted-syntax` rules scoped to `resources/js/admin/**` for hex colours, gradients and a `boxShadow` key, exempting `theme/tokens.ts` and `utils/markdownSx.ts`, and verify by adding a temporary violation that errors, removing it, and getting a clean `npm run lint`
- [ ] 8.2 Write `.claude/context/admin-design.md` (where each decision lives, the identity rule, the screenshot routine, how to restyle a page later) and update the `CLAUDE.md` topic and Blade component tables, and verify the files reflect the final code. Both files are gitignored, so they stay out of commits

## 9. Final verification

- [ ] 9.1 Compare chat screenshots with the 1.3 baseline, and verify the chat looks unchanged
- [ ] 9.2 Check flash messages, log out on both screen widths, back links, `aria-current`, keyboard focus, reduced motion and 360-pixel overflow in the browser, and verify each `admin-shell` scenario not covered by a test
- [ ] 9.3 Run `vendor/bin/phpunit` filtered to `BreadcrumbsTest`, `PostVisibilityTest`, `DesignReferenceTest`, `AdminHubsTest` and `CommentModerationTest`, plus `npx tsc --noEmit`, `npm run lint`, `npm run build` and `openspec validate add-blog-breadcrumbs-and-admin-shell --strict`, and verify all pass. Then ask the developer whether to run the full test suite
- [ ] 9.4 Run the `openspec-verifier` and `laravel-reviewer` agents over the branch, and fix confirmed findings, and verify by re-running the affected checks
- [ ] 9.5 Send the developer final screenshots and wait for approval before archiving or merging to `develop`. Production deployment is developer-owned: pull, `composer install`, `npm ci`, `npm run build`, `php artisan optimize:clear`
