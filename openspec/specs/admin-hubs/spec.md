# admin-hubs Specification

## Purpose

Groups the Admin Dashboard's tiles into hubs for areas of work — Blog for blogging, Employment for the job search, Site Settings for running the site — so the dashboard lists areas rather than individual pages.

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

The system SHALL provide a Blog hub page at `/admin/blog` that shows, as navigation tiles and in this order, Blog Admin (opening the configured Canvas path as a full page load), Comments (opening the moderation queue) and View Blog (opening the public blog at `/blog` as a full page load). The Blog Admin tile SHALL be shown only to a user holding `manage-blog`; the Comments tile only to a user who may moderate comments; the View Blog tile to every user who may open the hub. The hub page SHALL be reachable by any user who may moderate comments. The configured Canvas path SHALL NOT be `/admin/blog`, so Canvas never shadows the hub.

#### Scenario: Blog administrator

- **WHEN** a user holding `manage-blog` opens `/admin/blog`
- **THEN** the Blog Admin, Comments and View Blog tiles are shown, in that order

#### Scenario: Comment moderator only

- **WHEN** a user holding `manage-comments` but not `manage-blog` opens `/admin/blog`
- **THEN** only the Comments and View Blog tiles are shown

#### Scenario: Blog Admin opens outside the admin app

- **WHEN** the user activates the Blog Admin tile
- **THEN** the browser performs a full page load of the configured Canvas path

#### Scenario: View Blog opens the public blog

- **WHEN** the user activates the View Blog tile
- **THEN** the browser performs a full page load of `/blog`

#### Scenario: No blog permission

- **WHEN** a signed-in user who holds neither `manage-blog` nor `manage-comments` requests `/admin/blog`
- **THEN** the request is refused with 403

### Requirement: Site Settings hub

The system SHALL provide a Site Settings hub page at `/admin/settings` that shows, as navigation tiles and in this order: Mail Preview, Site Navigation, Roles & Permissions. The Mail Preview and Site Navigation tiles SHALL be shown only to a user holding `edit-settings`, and the Roles & Permissions tile only to a user holding `view-roles`. The hub page SHALL be reachable by any user holding either permission.

#### Scenario: Hub tiles in order

- **WHEN** a user holding `edit-settings` and `view-roles` opens `/admin/settings`
- **THEN** the page shows the tiles Mail Preview, Site Navigation and Roles & Permissions, in that order

#### Scenario: Settings permission only

- **WHEN** a user holding `edit-settings` but not `view-roles` opens `/admin/settings`
- **THEN** only the Mail Preview and Site Navigation tiles are shown

#### Scenario: Roles permission only

- **WHEN** a user holding `view-roles` but not `edit-settings` opens `/admin/settings`
- **THEN** only the Roles & Permissions tile is shown

#### Scenario: No settings permission

- **WHEN** a signed-in user who holds neither `edit-settings` nor `view-roles` requests `/admin/settings`
- **THEN** the request is refused with 403

#### Scenario: Anonymous visitor

- **WHEN** an unauthenticated visitor requests `/admin/settings`
- **THEN** they are redirected to login

### Requirement: Admin Dashboard lists hubs in place of the grouped pages

The Admin Dashboard SHALL show the Blog hub, the Employment hub, AI Tools and the Site Settings hub as its tiles, in that order, each subject to its own permission. The dashboard SHALL NOT show tiles for Applications, Application Metrics, Resume Management, Cover Letter Management, Blog Admin, Comments, Mail Preview, Site Navigation or Roles & Permissions.

#### Scenario: Dashboard order

- **WHEN** a user holding every admin permission opens `/admin`
- **THEN** the tiles are Blog, Employment, AI Tools and Site Settings, in that order

#### Scenario: Moved tiles are gone

- **WHEN** any user opens `/admin`
- **THEN** no tile links directly to Applications, Application Metrics, Resume Management, Cover Letter Management, Blog Admin, Comments, Mail Preview, Site Navigation or Roles & Permissions

### Requirement: Admin navigation lists the hubs in dashboard order

The admin navigation SHALL list the hubs in the Admin Dashboard's tile order after the Admin Dashboard itself, each with its tiles as its pages, subject to the same permission filtering as the hub page.

#### Scenario: Navigation order

- **WHEN** a user holding every admin permission opens any admin page that shows the navigation
- **THEN** the navigation lists Admin Dashboard, Blog, Employment, AI Tools and Site Settings, in that order

#### Scenario: Employment's pages

- **WHEN** a user holding `edit-resume` opens a page in the Employment hub
- **THEN** Employment's listed pages are the Employment hub's tiles in hub order

#### Scenario: Without blog permissions

- **WHEN** a user holding `edit-resume` but no blog permission opens any admin page that shows the navigation
- **THEN** the navigation's first area after the Admin Dashboard is Employment

### Requirement: Pages moved into a hub link back to it

The existing pages grouped into a hub SHALL keep their URLs, except Applications and Application Metrics, which SHALL be served under `/admin/employment` (see *Applications and Application Metrics live under Employment*). The back links of Resume Management, Cover Letter Management, Applications and Application Metrics SHALL read "Back to Employment" and point at `/admin/employment`; the Comments page's back link SHALL read "Back to Blog" and point at `/admin/blog`; the back links of Mail Preview, Site Navigation and Roles & Permissions SHALL read "Back to Site Settings" and point at `/admin/settings`.

#### Scenario: Resume Management back link

- **WHEN** the user opens `/admin/resume`
- **THEN** its back link reads "Back to Employment" and points at `/admin/employment`

#### Scenario: Applications back link

- **WHEN** the user opens `/admin/employment/applications`
- **THEN** its back link points at `/admin/employment`, not at Resume Management

#### Scenario: Comments back link

- **WHEN** the user opens `/admin/comments`
- **THEN** its back link reads "Back to Blog" and points at `/admin/blog`

#### Scenario: Site Settings back links

- **WHEN** the user opens `/admin/mail-preview`, `/admin/site-settings` or `/admin/roles-permissions`
- **THEN** its back link reads "Back to Site Settings" and points at `/admin/settings`

#### Scenario: Existing URLs still work

- **WHEN** the user requests `/admin/resume`, `/admin/cover-letters`, `/admin/mail-preview`, `/admin/site-settings` or `/admin/roles-permissions`
- **THEN** the page is served at that URL, as before

### Requirement: Applications and Application Metrics live under Employment

The Applications pages SHALL be served under `/admin/employment/applications`: the list at `/admin/employment/applications`, New Session at `/admin/employment/applications/new`, and an application's Discussion page at `/admin/employment/applications/{application}`. Application Metrics SHALL be served at `/admin/employment/metrics`. The Applications JSON API SHALL be served under `/api/admin/employment/applications`. All of them SHALL keep the same permission (`edit-resume`), parameters and behavior as before. Every admin link, back link, API call and post-action redirect to these pages or endpoints SHALL use the new URLs. The former URLs under `/admin/resume/applications`, `/admin/resume/metrics` and `/api/admin/resume/applications` SHALL NOT serve or redirect; they SHALL answer 404.

#### Scenario: Applications pages at the new URLs

- **WHEN** a user holding `edit-resume` opens `/admin/employment/applications`, `/admin/employment/applications/new` or `/admin/employment/applications/{application}` for an existing application
- **THEN** the list, New Session or that application's Discussion page is served

#### Scenario: Application Metrics at the new URL

- **WHEN** a user holding `edit-resume` opens `/admin/employment/metrics?range=90d`
- **THEN** Application Metrics is served for that range

#### Scenario: API at the new URL

- **WHEN** a user holding `edit-resume` saves, applies, passes, chats on, finalizes or records a status update for an application
- **THEN** the page calls `/api/admin/employment/applications/…` and the request succeeds as it did at the old URL

#### Scenario: Permission still required

- **WHEN** a signed-in user without `edit-resume` requests `/admin/employment/applications`, `/admin/employment/metrics` or any `/api/admin/employment/applications` endpoint
- **THEN** access is refused

#### Scenario: Former URLs are gone

- **WHEN** anyone requests `/admin/resume/applications`, `/admin/resume/applications/{application}`, `/admin/resume/metrics` or `/api/admin/resume/applications`
- **THEN** the response is 404 and no redirect is given

#### Scenario: Former Targeted Resume Builder URLs land on the new pages

- **WHEN** the user requests `/admin/resume/targeted-builder`, `/admin/resume/targeted-builder/new` or `/admin/resume/targeted-builder/{conversation}` for a conversation with an application
- **THEN** they receive a 301 redirect to the matching `/admin/employment/applications` URL

#### Scenario: Post-action redirects use the new URL

- **WHEN** an application is created, analysis begins, an application is deleted, or a targeted resume is regenerated or discarded from its application
- **THEN** any redirect the response gives points under `/admin/employment/applications`
