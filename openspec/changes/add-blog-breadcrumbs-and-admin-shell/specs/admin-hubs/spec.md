## REMOVED Requirements

### Requirement: App bar lists the Employment hub first

**Reason**: The new admin shell replaces the app bar with a navigation that is not a bar. The ordering and permission rules carry over to "Admin navigation lists the Employment hub first".

**Migration**: Use "Admin navigation lists the Employment hub first". The `adminNav` prop keeps its current shape and order.

## ADDED Requirements

### Requirement: Admin navigation lists the Employment hub first

The admin navigation SHALL list the Employment hub, with its tiles as its pages, as the first area after the Admin Dashboard, subject to the same permission filtering as the hub page.

#### Scenario: Navigation order

- **WHEN** a user holding `edit-resume` opens any admin page that shows the navigation
- **THEN** the navigation's first area after the Admin Dashboard is Employment, and its pages are the Employment hub's tiles in hub order
