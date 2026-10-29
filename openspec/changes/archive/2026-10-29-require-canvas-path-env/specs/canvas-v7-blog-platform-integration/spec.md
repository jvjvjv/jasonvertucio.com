## ADDED Requirements

### Requirement: Canvas admin base path is set by the environment
The system SHALL take the Canvas admin base path exclusively from the `CANVAS_PATH` environment variable, with no default value in application configuration. The value SHALL have no leading or trailing slash. Links to Canvas post edit and stats pages SHALL be built from the configured path.

#### Scenario: Path defined in the environment
- **WHEN** `CANVAS_PATH` is set to a value and the application boots
- **THEN** the Canvas admin is served under that path, and edit and stats links on blog pages use it

#### Scenario: Path not defined
- **WHEN** `CANVAS_PATH` is not set
- **THEN** the application configuration holds no Canvas path and no `canvas` default is applied

## MODIFIED Requirements

### Requirement: Canvas admin access uses host authentication only
The system SHALL authenticate Canvas admin access, served at the configured Canvas path, exclusively through the host application's `web` guard. No separate `canvas` authentication guard, Canvas-managed password, or Canvas-owned login/logout route SHALL exist or be reachable.

#### Scenario: Unauthenticated visitor hits /canvas
- **WHEN** an unauthenticated visitor requests the configured Canvas path
- **THEN** they are redirected to the host application's standard login flow, not a Canvas-specific login page

#### Scenario: Authenticated host user without Canvas access
- **WHEN** a user authenticated on the host `web` guard, who has no corresponding `canvas_users` row, requests the configured Canvas path
- **THEN** the response is HTTP 403

#### Scenario: Authenticated host user with Canvas access
- **WHEN** a user authenticated on the host `web` guard, who has a `canvas_users` row, requests the configured Canvas path
- **THEN** the Canvas admin SPA loads successfully
