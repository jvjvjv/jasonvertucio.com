## ADDED Requirements

### Requirement: Only users with `manage-ai-tools` can see or set the tool payload preference

The profile page SHALL show the tool-payload-visibility toggle only to a user who holds the `manage-ai-tools` permission. A user without that permission SHALL NOT see the toggle, and any attempt to set the preference without the permission SHALL be rejected.

#### Scenario: Permitted user sees the toggle

- **WHEN** a user with `manage-ai-tools` views their profile page
- **THEN** the tool-payload-visibility toggle is present, reflecting their current `show_tool_payloads` value

#### Scenario: Unpermitted user never sees the toggle

- **WHEN** a user without `manage-ai-tools` views their profile page
- **THEN** no tool-payload-visibility toggle or section is present on the page

#### Scenario: Setting the preference without permission is rejected

- **WHEN** a request to update `show_tool_payloads` is made by a user without `manage-ai-tools`
- **THEN** the request is rejected and the user's stored preference is unchanged

### Requirement: The preference persists across sessions

Enabling or disabling `show_tool_payloads` SHALL persist for that user across requests and sessions until changed again.

#### Scenario: Preference survives a new session

- **WHEN** a user with `manage-ai-tools` enables the toggle, then logs out and back in
- **THEN** the toggle still shows enabled on their next profile page visit, and tool payloads are still visible to them in chat
