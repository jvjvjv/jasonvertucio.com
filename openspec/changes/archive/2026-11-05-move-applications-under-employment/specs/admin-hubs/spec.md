## MODIFIED Requirements

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

## ADDED Requirements

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
