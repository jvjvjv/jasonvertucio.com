## MODIFIED Requirements

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
