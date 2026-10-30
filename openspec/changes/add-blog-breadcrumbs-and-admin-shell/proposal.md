## Why

Blog pages give a reader no sense of where they are or a way back up: a topic listing, a tag listing and a post offer nothing but the site menu. Separately, the admin's look is scattered across a small MUI theme, a top bar and shared components, with no single place a design pass can work from. That makes it hard to improve the admin deliberately, and its navigation hides most destinations inside dropdown menus.

## What Changes

**Blog breadcrumbs**

- Every blog page shows a breadcrumb trail: `/blog`, `/blog/topics`, `/blog/tags`, each topic and tag listing, and each post. The trail also appears on empty listings and on an author's preview of an unpublished post.

**New admin shell and design foundation (MUI stays; same visual identity)**

- A new admin shell replaces the top app bar: a persistent side navigation on wide screens and a menu-button drawer on narrow ones. The current area's pages are listed in the navigation, and the current page is marked.
- A single design token source now defines the admin's colour, type, spacing, radius and border values, and the MUI theme is built from it.
- Shared components (page header, hub tiles, data table, pagination, empty rows, status chips, confirm dialog) and MUI's defaults are redesigned, so the Dashboard, the hubs and every inner page pick up the new look without page edits.
- A new permission-gated design reference page at `/admin/design-reference` shows the tokens and shared components in their states using sample data. It gives the `/frontend-design` skill and the developer one place to review the design system.
- Lint rules stop new hard-coded colours, gradients and box shadows from entering admin code, and a topic doc records the design rules and where each decision lives.
- The chat bundle currently imports the admin theme. It gets its own frozen copy, so the public chat does not change.

**Unchanged by this change**

- Admin URLs, permissions, back links, flash messages, log out, the full-screen Discussion and Company Intel session pages, and the Canvas blog editor.
- Page-by-page restyling, dark mode and removing MUI. Each is left to later changes.

## Capabilities

### New Capabilities

- `blog-breadcrumbs`: The breadcrumb trail on each blog page, including its structure, accessibility, escaping and empty and preview states.
- `admin-shell`: The admin's navigation shell, design token rules and design reference page.

### Modified Capabilities

- `admin-hubs`: "App bar lists the Employment hub first" is replaced by "Admin navigation lists the Employment hub first", because the app bar no longer exists. The ordering and permission filtering are unchanged.

## Impact

- **Blade/PHP**: a new `<x-breadcrumbs>` component; `app/Http/Controllers/BlogController.php`; `resources/views/blog/{list,single,topics,tags}.blade.php`; a new route and an `AdminController` action for the design reference.
- **Admin frontend**: `resources/js/admin/theme.ts` is replaced by a token and theme module; `resources/js/admin/layouts/` and `resources/js/admin/components/` are rebuilt behind their existing props; `pages/ai/conversations/Show.tsx` loses its one hard-coded colour; a new `DesignReference` page is added.
- **Chat bundle**: `resources/js/chat/app.tsx` points at a frozen copy of the current theme. `markdownSx.ts` stays as it is because both bundles use it.
- **Tooling and docs**: `eslint.config.mjs`; a new `.claude/context/admin-design.md`; the `CLAUDE.md` topic table and Blade component table.
- **Tests**: new feature tests for breadcrumbs and the design reference. The admin has no JS test runner, so shell behaviour is checked with TypeScript, ESLint, a production build and browser screenshots.
- **Dependencies, database and APIs**: no new dependencies or migrations. The `adminNav` Inertia prop and the JSON APIs keep their current shape.
