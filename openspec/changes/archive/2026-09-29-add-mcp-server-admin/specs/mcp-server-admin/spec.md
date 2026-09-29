## Purpose

Lets a site administrator register external MCP servers, see their sync health and tool catalog, and remove them, so their tools can be granted to AI systems.

## ADDED Requirements

### Requirement: MCP server admin is restricted to AI tool managers

Every MCP server admin page and action SHALL require an authenticated user holding the `manage-ai-tools` permission. Guests SHALL be redirected to login. Signed-in users without the permission SHALL receive 403.

#### Scenario: Guest is sent to login

- **WHEN** a guest requests the MCP server list
- **THEN** the response redirects to the login page

#### Scenario: User without permission is forbidden

- **WHEN** a signed-in user without `manage-ai-tools` requests the MCP server list, or submits any MCP server create, update, sync or delete
- **THEN** the response is 403 and nothing is written

### Requirement: Servers are listed with their sync health

The MCP server list SHALL show every server that hasn't been deleted, ordered by name. Each entry SHALL show its name, slug, URL, auth type, enabled state, last-synced time, last sync error (if any), and how many tools it has. The list SHALL NOT include any credential value.

#### Scenario: Server with a failed sync

- **WHEN** a server's last sync failed with an error
- **THEN** its list entry shows that error message and its last-synced time

#### Scenario: Credentials never reach the page

- **WHEN** a server stored with a bearer token is listed
- **THEN** the page props show its auth type as `bearer` and contain no token value

### Requirement: Administrators can create a server

An administrator SHALL be able to create a server by giving it a name, a slug, an HTTP(S) URL, an optional timeout (seconds), an enabled flag, and auth. The slug SHALL be 1–24 lowercase letters, digits or hyphens, starting and ending with a letter or digit, and unique among servers. Auth SHALL be one of: none; bearer (token required); headers (at least one header name and value); or OAuth client credentials (client ID required; client secret, scope and token endpoint optional; a token endpoint, if given, must be a URL). Creating a server SHALL immediately attempt to sync its tool catalog. A server SHALL be saved even if that sync fails, and the outcome SHALL be reported to the administrator.

#### Scenario: Successful create and sync

- **WHEN** an administrator creates a server whose endpoint responds with three tools
- **THEN** the server is saved, its catalog holds three tools, and a success message reports three tools synced

#### Scenario: Unreachable server is still saved

- **WHEN** an administrator creates a server whose URL doesn't respond
- **THEN** the server is saved with its sync error recorded, and the administrator sees a warning containing that error rather than a failure page

#### Scenario: Invalid input is rejected

- **WHEN** an administrator submits a slug containing uppercase letters or underscores, a slug already in use, a non-HTTP URL, or bearer auth with no token
- **THEN** the form redisplays with a validation error on the offending field and no server is created

### Requirement: Administrators can edit a server without re-entering secrets

An administrator SHALL be able to change a server's name, URL, timeout, enabled flag and auth. The slug SHALL be shown but SHALL NOT be changeable after creation. The edit form SHALL NOT receive stored credential values. Saving without changing auth SHALL keep the stored credentials. Choosing a different auth type or entering new credential values SHALL replace them. Saving SHALL re-sync the catalog and report the outcome the same way create does.

#### Scenario: Unchanged auth keeps the stored secret

- **WHEN** an administrator edits a bearer-auth server's name and saves without touching auth
- **THEN** the server still authenticates with the original token

#### Scenario: Slug cannot be changed

- **WHEN** an update request carries a different slug
- **THEN** the server's slug is unchanged

#### Scenario: Switching auth type replaces credentials

- **WHEN** an administrator changes a bearer-auth server to auth type none and saves
- **THEN** the server no longer stores a token

### Requirement: Administrators can sync a server on demand

An administrator SHALL be able to trigger a catalog sync for a single server from the admin area. The result SHALL be reported as the number of tools stored and the number unavailable, or the error. A failed sync SHALL NOT remove the previously synced catalog.

#### Scenario: Manual sync reports counts

- **WHEN** an administrator syncs a server whose endpoint now responds with one more tool than before
- **THEN** the catalog includes the new tool and the message reports the new totals

### Requirement: A server's tool catalog is visible

The server edit page SHALL list the server's synced tools. Each SHALL show its exposed name (`{slug}__{tool}`), its remote name, its description, and whether it can be offered to a model. A tool that can't be offered SHALL show the reason.

#### Scenario: Unrepresentable tool is flagged

- **WHEN** a synced tool's input schema couldn't be converted
- **THEN** the catalog shows that tool as unavailable, with its reason

### Requirement: Administrators can delete a server

An administrator SHALL be able to delete a server after an explicit confirmation. Deleting SHALL withdraw its tools from every AI system. The confirmation message SHALL state how many AI systems had granted one of its tools. AI systems' stored grants SHALL NOT be rewritten by the delete.

#### Scenario: Delete reports affected systems

- **WHEN** an administrator deletes a server whose tools two AI systems had granted
- **THEN** the server no longer appears in the list, its tools are no longer offered to any turn, and the success message says two AI systems were affected

### Requirement: MCP servers are reachable from the admin navigation

The admin navigation SHALL include an "MCP Servers" entry, linking to the server list, shown only to users holding `manage-ai-tools`.

#### Scenario: Navigation entry for AI tool managers

- **WHEN** a user with `manage-ai-tools` views the AI admin landing page
- **THEN** its navigation blocks include an "MCP Servers" entry linking to `/admin/ai/mcp-servers`
