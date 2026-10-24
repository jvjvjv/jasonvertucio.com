# Canvas V7 Blog Platform Integration Specification

## Purpose

Defines the observable behavior of the Canvas v7 blog platform integration after the v6→v7 upgrade: who can reach the Canvas admin SPA and how, that existing published content and comment linkage survive the schema migration intact, and that the site's own auth/comment systems are unaffected by Canvas's auth rewrite.

## Requirements

### Requirement: Canvas admin access uses host authentication only
The system SHALL authenticate Canvas admin (`/canvas`) access exclusively through the host application's `web` guard. No separate `canvas` authentication guard, Canvas-managed password, or Canvas-owned login/logout route SHALL exist or be reachable.

#### Scenario: Unauthenticated visitor hits /canvas
- **WHEN** an unauthenticated visitor requests `/canvas`
- **THEN** they are redirected to the host application's standard login flow, not a Canvas-specific login page

#### Scenario: Authenticated host user without Canvas access
- **WHEN** a user authenticated on the host `web` guard, who has no corresponding `canvas_users` row, requests `/canvas`
- **THEN** the response is HTTP 403

#### Scenario: Authenticated host user with Canvas access
- **WHEN** a user authenticated on the host `web` guard, who has a `canvas_users` row, requests `/canvas`
- **THEN** the Canvas admin SPA loads successfully

### Requirement: Existing published content remains intact and publicly viewable
The system SHALL preserve all previously published blog posts, their tags, their topic assignment, and their comment linkage through the v6-to-v7 schema migration, such that public blog pages render identically to before the migration.

#### Scenario: Public blog index after migration
- **WHEN** a visitor requests `/blog` after the migration is complete
- **THEN** every post that was publicly visible before the migration is still listed, with the same title, slug, and publish date

#### Scenario: Individual post page after migration
- **WHEN** a visitor requests an existing post's `/blog/{slug}` page after the migration
- **THEN** the post content, its tags, and its single topic render correctly, and any existing comments on that post remain attached and visible

#### Scenario: Post with multiple pre-migration topics
- **WHEN** a post that had more than one topic assigned under the v6 multi-topic pivot is migrated to v7's single-topic model
- **THEN** the post retains exactly one topic post-migration, chosen deterministically (not silently dropped to null), and this resolution is recorded for review

### Requirement: Host comment system is unaffected by the Canvas auth rewrite
The site's native comment system SHALL continue to submit, moderate, and display comments against Canvas posts without depending on the removed `canvas` guard or any Canvas-owned authentication state.

#### Scenario: Comment submission after migration
- **WHEN** a visitor submits a comment on an existing blog post after the migration
- **THEN** the comment is created and linked to the correct post exactly as it was before the migration, independent of any Canvas auth guard

#### Scenario: Comment moderation after migration
- **WHEN** an admin with `manage-blog` permission visits `/admin/comments` after the migration
- **THEN** the moderation queue loads and functions as before, and `/canvas`'s catch-all route does not intercept `/admin/comments`
