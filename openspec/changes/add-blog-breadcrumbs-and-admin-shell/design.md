## Context

See `proposal.md` for motivation and the `specs/` directory for required behaviour. The developer decided four things up front: keep MUI and build a new shell and tokens on it, keep the existing visual identity, put breadcrumbs on all blog pages, and ship both pieces as one change.

**Blog.** `BlogController` renders four Blade views. `blog/list` serves the index and the topic and tag listings. `/blog/{slug}` falls through from post to topic to tag (`topicsOrTags()`), so one template and three URL shapes reach the same listing. The topic and tag actions do not currently pass the topic or tag to the view.

**Admin.** 47 page files wrap themselves in `AdminLayout` inline, with no Inertia persistent layout, so the layout remounts on most visits. Current numbers:

| Measure | Count |
| --- | --- |
| Files using `PageHeader` | 43 |
| Files using `DataTable` | 13 |
| Files with a raw MUI table | 6 |
| Hub pages using `NavGrid` | 5 |
| `sx=` props | 572 |
| Hard-coded hex colours outside `theme.ts` | 8 (7 in `markdownSx.ts`, 1 in `ai/conversations/Show.tsx`) |

Pages already use theme names rather than literals, so a theme change will reach them.

**Coupling that shapes the work:**

- `resources/js/chat/app.tsx` imports `theme` from `admin/theme`, so an admin theme change would also restyle the public chat.
- Chat bubble components in `resources/js/components/` import `admin/utils/markdownSx`, and they also render inside the admin Discussion page.
- `adminNav` lists the Dashboard with every dashboard tile as its children. Mail Preview and Site Navigation appear only there, and `isPathActive()` treats `/admin` as active on every admin page.

**Constraints.** The project rules forbid gradients and box shadows. A previous whole-site redesign was rejected, so the existing identity wins over any "distinctive point of view" guidance, and the developer sees one page before the design spreads to the rest. There is no JS test runner.

## Goals / Non-Goals

**Goals:**

- Give `/frontend-design` work a clear seam: tokens in one file, component defaults in the theme, a few shared primitives, and one page that shows them all.
- Change the admin's whole appearance with no page file edited, apart from removing the one hard-coded colour.
- Keep breadcrumbs and the admin work independently reviewable and revertible, in separate commits.

**Non-Goals:**

- Page-by-page layout changes, including the Resume Editor, Metrics and Discussion pages. Pages are only fixed if the new shell breaks them.
- Removing MUI, adding Tailwind to the admin bundle, or adding dark mode.
- Breadcrumbs in the admin. Back links are specified by `admin-hubs` and stay.
- Breadcrumb JSON-LD, a topic segment in post trails, or changes to blog page titles and card markup.
- Changes to the public site's design or to the chat's appearance.

## Decisions

### Breadcrumbs

**1. The controller builds the trail and an anonymous Blade component renders it.** Each `BlogController` action passes `breadcrumbs` to its view through a private helper that prepends Home and Blog. `<x-breadcrumbs :items="$breadcrumbs" />` renders it at the top of each view's `main` section, outside the populated/empty branch.

- Alternative rejected: deriving the trail from the URL inside the component. `/blog/{slug}` has three meanings, and the labels need model names the URL does not have.
- Because the fall-through calls `topicList()` and `tagList()`, the short addresses get the canonical trail with no extra code.
- Alternative rejected: a `@yield` slot in `layout.blade.php`. That layout serves non-blog pages, and four includes are easier to follow.

**2. A post's trail is Home, Blog, title.** This follows the address (`/blog/{slug}`), avoids long trails on phones, and matches the page, which doesn't show the topic today.

### Admin foundation

**3. Tokens are a plain TypeScript module, and the theme is built from it.** `resources/js/admin/theme/` holds:

- `tokens.ts`: plain values with no MUI import
- `components.ts`: MUI `components` overrides
- `index.ts`: `createTheme(...)`, still exporting `theme`, so `admin/app.tsx` barely changes

MUI's `cssVariables` mode stays off because nothing here needs runtime theming, and it can break `alpha(theme.palette…)` calls.

- Alternative rejected: sharing `resources/css/theme.css` through CSS variables. The admin bundle doesn't load Tailwind or that file, and this would add a load-order dependency to save six values. `tokens.ts` carries a comment pointing to `theme.css`, and a review check covers drift.

**4. Chat gets a frozen copy of today's theme.** The current `theme.ts` moves unchanged to `resources/js/chat/theme.ts`, and `chat/app.tsx` imports it. This meets the "chat is not affected" requirement exactly and lets the two themes diverge.

- Alternative rejected: a shared base theme. Any change to the base would keep leaking into chat.
- `markdownSx.ts` stays as it is and gets a named lint exemption, because both bundles use it. Chat bubbles shown in the Discussion page pick up the admin theme there, which is the intended result.

**5. `AdminLayout` keeps its exact props.** `title`, `noMargin`, `showChrome` and `maxContainerWidth` keep working, so no page changes. `showChrome={false}` still hides all chrome. The flash `Snackbar` logic moves unchanged. The fixed save and download buttons in the Resume Editor and Cover Letter Preview anchor to the right edge, clear of a left sidebar.

**6. The shell is a left sidebar on `md` and up, and a top bar with a drawer below that.** The public homepage already has a fixed primary-blue sidebar (`md:w-52 lg:w-64`), so this extends the identity rather than importing a new pattern. It also makes the many destinations visible. The direct alternative is keeping a restyled top bar, and if the developer prefers it at the review gate, `admin-shell` still holds because its requirements don't depend on placement.

**7. The sidebar has no expand state, because the path decides what's open.** Activating an area goes to its hub, and that hub's pages then appear.

- This is deliberate: `AdminLayout` remounts on navigation, so stored expansion state would flicker or need persistence. It also removes toggle buttons and `aria-expanded` handling, and keeps two clicks to reach any page, the same as today's dropdowns.
- Transitions do not run on mount.

**8. Navigation is derived in a pure client function, with no server change.** `navHelpers.ts` gains:

- `buildNavAreas(adminNav)`: returns the Dashboard, then its children in tile order, each with its pages looked up from the matching top-level `adminNav` entry.
- `currentDestination(path, areas)`: returns the longest matching href, treating `/admin` as an exact match only.

The `adminNav` prop and `getAppBarItems()` keep their shape, so the tests that pin them still pass.

**9. Shared components are rebuilt behind their current props.** `PageHeader`, `NavGrid`, `DataTable`, `Pagination`, `EmptyTableRow`, `StatusChip` and `ConfirmDialog` keep their signatures. `DesktopNavItem` and `MobileNavSection` are replaced. `LogoutForm` stays, with its `bar` and `menu` variants adapted to the new layout.

**10. "Friendly to `/frontend-design`" means three concrete things:**

- **One page to review.** `/admin/design-reference` (`AdminController::designReference`, `can:edit-settings` inside the `/admin` group) renders tokens and component states from sample data in the page file, with no props from the server. Controls in the sample only change local state. It is unlinked because `admin-hubs` pins the dashboard's tiles.
- **Guardrails.** ESLint `no-restricted-syntax` rules, scoped to `resources/js/admin/**`, reject hex colour literals, `linear-gradient`/`radial-gradient` and a `boxShadow` key. `theme/tokens.ts` and `utils/markdownSx.ts` are exempt.
- **A map.** `.claude/context/admin-design.md` records where each decision lives, the identity rule, the screenshot routine and how to restyle a page later. `CLAUDE.md` gains a row for it.

### Starting design plan

This is the plan for the review gate, not a final design. Values come from the current theme and `resources/css/theme.css`.

| Token | Value | Use |
| --- | --- | --- |
| `blue` | `#1b587c` | sidebar, primary actions |
| `orange` | `#b35e06` | current-page marker, secondary actions |
| `ink` | `#25292c` | body text |
| `paper` | `#f9fafb` | page background |
| `surface` | `#ffffff` | tables, dialogs, menus |
| `line` | `#e5e7eb` | borders, which stand in for shadows |

- **Type:** Josefin Sans for page titles and area names. Montserrat for everything else, including controls and table text. Sentence case throughout. The 21 existing `h6` uses stay in Montserrat.
- **The one memorable element:** a full-height blue sidebar with the current page marked by a solid orange bar. Everything else stays quiet.
- **Hub pages:** the identical-card tile grid becomes a denser directory of rows (icon, label, one-line description), which avoids the generic card-grid look. `NavGrid`'s props don't change.
- **Tables:** quiet header row, hairline row rules, and tight spacing for repeated use.

```text
md and up                               below md
+----------+---------------------+      +----------------------+
| Jason V. | Page title  [action]|      | [=] Jason Vertucio   |
|          |                     |      +----------------------+
| Dashboard| table / form / rows |      | Page title           |
| Employ.  |                     |      | [action]             |
| |Applica.|                     |      |                      |
| |Metrics |                     |      | table / form / rows  |
| Mail     |                     |      |                      |
| AI Tools |                     |      |                      |
| ...      |                     |      |                      |
| Log out  |                     |      |                      |
+----------+---------------------+      +----------------------+
```

## Risks / Trade-offs

- **The developer may dislike the look once it's live, as happened with the last redesign.** → Tasks stop at a review gate after the tokens, the shell and one hub and one list page exist. Nothing else is rebuilt until the developer reacts, and nothing merges to `develop` without screenshots.
- **Global MUI overrides can break a page nobody looks at.** → The screenshot pass covers a set chosen for variety: dashboard, hub, list, form, dialog, charts, Resume Editor, date picker, and a full-screen session. Regressions are fixed in the theme first and in a page only as a last resort.
- **Uncommitted developer edits sit in `resources/views/blog/list.blade.php` and `resources/css/blog.css`.** → The first file overlaps breadcrumb work. Implementation preserves those edits, keeps them out of its commits, and tells the developer they are still pending.
- **The new lint rules may flag existing code.** → Known hits are the two `rgba(...)` values in the removed `DesktopNavItem`, which the hex and gradient rules would not match anyway, the `boxShadow` in the replaced `AdminLayout`, and the hex in `conversations/Show.tsx`. The full `npm run lint` run confirms nothing else trips.
- **The theme copies can drift between chat and admin.** → This is accepted. A later change can restyle chat deliberately.
- **Sidebar width takes space from wide tables.** → Table containers already scroll horizontally, and the review gate includes a wide list page at 1280 pixels.
- **Comment-only moderators still see an Admin Dashboard entry that answers 403.** This already happens today, because its entry has `can: null`. → It stays as is, since this change is not about navigation permissions.

## Migration Plan

There are no database or API changes. Developer-owned deployment steps follow the normal process: pull, build assets, clear caches. No queue worker restart is needed for frontend and Blade changes, though the normal deploy routine still applies. To roll back, revert the merge. The two sets of commits can be reverted independently.

## Open Questions

- The final token values and the sidebar's exact treatment are settled at the review gate. They don't affect the specs or the task breakdown.
