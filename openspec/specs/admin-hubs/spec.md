# admin-hubs Specification

## Purpose

Groups the Admin Dashboard's tiles into hubs for areas of work — Employment for the job search, Blog for blogging — so the dashboard lists areas rather than individual pages.

## Requirements

### Requirement: Employment hub

The system SHALL provide an Employment hub page at `/admin/employment` that shows, as navigation tiles and in this order: Applications, Application Metrics, Resume Management, Cover Letter Management, Company Intel. Each tile SHALL be shown only to a user holding that tile's permission. The hub page SHALL be reachable by any user who may open the Admin Dashboard.

#### Scenario: Hub tiles in order

- **WHEN** a user holding `edit-resume` opens `/admin/employment`
- **THEN** the page shows the tiles Applications, Application Metrics, Resume Management, Cover Letter Management and Company Intel, in that order

#### Scenario: Tiles are filtered by permission

- **WHEN** a user who may open the Admin Dashboard but does not hold `edit-resume` opens `/admin/employment`
- **THEN** no tile whose page requires `edit-resume` is shown

#### Scenario: Anonymous visitor

- **WHEN** an unauthenticated visitor requests `/admin/employment`
- **THEN** they are redirected to login

### Requirement: Blog hub

The system SHALL provide a Blog hub page at `/admin/blog` that shows, as navigation tiles and in this order, Blog Admin (opening the configured Canvas path as a full page load) and Comments (opening the moderation queue). The Blog Admin tile SHALL be shown only to a user holding `manage-blog`; the Comments tile only to a user who may moderate comments. The hub page SHALL be reachable by any user who may moderate comments. The configured Canvas path SHALL NOT be `/admin/blog`, so Canvas never shadows the hub.

#### Scenario: Blog administrator

- **WHEN** a user holding `manage-blog` opens `/admin/blog`
- **THEN** both the Blog Admin and Comments tiles are shown, in that order

#### Scenario: Comment moderator only

- **WHEN** a user holding `manage-comments` but not `manage-blog` opens `/admin/blog`
- **THEN** only the Comments tile is shown

#### Scenario: Blog Admin opens outside the admin app

- **WHEN** the user activates the Blog Admin tile
- **THEN** the browser performs a full page load of the configured Canvas path

#### Scenario: No blog permission

- **WHEN** a signed-in user who holds neither `manage-blog` nor `manage-comments` requests `/admin/blog`
- **THEN** the request is refused with 403

### Requirement: Admin Dashboard lists hubs in place of the grouped pages

The Admin Dashboard SHALL show the Employment hub as its first tile and the Blog hub as its last tile, each subject to its own permission. The dashboard SHALL NOT show tiles for Applications, Application Metrics, Resume Management, Cover Letter Management, Blog Admin or Comments. The remaining tiles SHALL keep their existing order between the two hubs.

#### Scenario: Dashboard order

- **WHEN** a user holding every admin permission opens `/admin`
- **THEN** the first tile is Employment, the last tile is Blog, and Mail Preview, AI Tools, Roles & Permissions and Site Navigation appear between them in that order

#### Scenario: Moved tiles are gone

- **WHEN** any user opens `/admin`
- **THEN** no tile links directly to Applications, Application Metrics, Resume Management, Cover Letter Management, Blog Admin or Comments

### Requirement: App bar lists the Employment hub first

The admin app bar SHALL list the Employment hub, with its tiles as children, as the first area after the Admin Dashboard, subject to the same permission filtering as the hub page.

#### Scenario: App bar order

- **WHEN** a user holding `edit-resume` opens any admin page
- **THEN** the app bar's first area after the Admin Dashboard is Employment, and its children are the Employment hub's tiles in hub order

### Requirement: Pages moved into a hub link back to it

The existing pages grouped into a hub SHALL keep their URLs. The back links of Resume Management, Cover Letter Management, Applications and Application Metrics SHALL read "Back to Employment" and point at `/admin/employment`; the Comments page's back link SHALL read "Back to Blog" and point at `/admin/blog`.

#### Scenario: Resume Management back link

- **WHEN** the user opens `/admin/resume`
- **THEN** its back link reads "Back to Employment" and points at `/admin/employment`

#### Scenario: Applications back link

- **WHEN** the user opens `/admin/resume/applications`
- **THEN** its back link points at `/admin/employment`, not at Resume Management

#### Scenario: Comments back link

- **WHEN** the user opens `/admin/comments`
- **THEN** its back link reads "Back to Blog" and points at `/admin/blog`

#### Scenario: Existing URLs still work

- **WHEN** the user requests `/admin/resume/applications`, `/admin/resume/metrics`, `/admin/resume` or `/admin/cover-letters`
- **THEN** the page is served at that URL, as before
