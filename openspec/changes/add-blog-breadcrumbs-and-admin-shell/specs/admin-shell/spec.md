## Purpose

Defines the admin section's navigation shell and the rules that keep its look in one place, so the admin can be redesigned deliberately without changing what any page does.

## ADDED Requirements

### Requirement: Navigation fits the screen

On a wide screen, the admin navigation SHALL stay visible beside the page content without needing to be opened. On a narrow screen, it SHALL be hidden behind a labelled menu button and open as a dismissible panel that closes when a destination is chosen. The panel SHALL be operable by keyboard. At a width of 360 pixels, the shell SHALL NOT cause horizontal page scrolling.

#### Scenario: Wide screen

- **WHEN** a signed-in administrator opens an admin page on a screen 1280 pixels wide
- **THEN** the navigation areas are visible beside the content without any menu being opened

#### Scenario: Narrow screen

- **WHEN** a signed-in administrator opens an admin page on a screen 360 pixels wide
- **THEN** the navigation is hidden, a labelled menu button is shown, and the page does not scroll horizontally

#### Scenario: Choosing a destination on a narrow screen

- **WHEN** the user opens the panel and chooses a destination
- **THEN** that page opens and the panel closes

#### Scenario: Dismissing the panel by keyboard

- **WHEN** the panel is open and the user presses Escape
- **THEN** the panel closes and focus returns to the menu button

### Requirement: Navigation lists every permitted area in dashboard order

The navigation SHALL list the Admin Dashboard first, followed by each area the user may reach, in the Admin Dashboard's tile order. An area or page the user lacks permission for SHALL NOT be listed.

#### Scenario: A user with every admin permission

- **WHEN** a user holding every admin permission opens any admin page
- **THEN** the navigation lists Admin Dashboard, Blog, Employment, AI Tools and Site Settings, in that order

#### Scenario: A user with narrow permissions

- **WHEN** a user holding `edit-resume` but not `manage-ai-tools` opens an admin page
- **THEN** Employment is listed and AI Tools is not

### Requirement: Navigation shows the current area's pages and marks the current page

Activating an area SHALL open that area's page. The navigation SHALL list the pages of the area containing the current page and SHALL NOT list the pages of any other area. Exactly one destination SHALL be marked as the current page, namely the one whose address most specifically matches the current address. The Admin Dashboard SHALL be marked only on `/admin` itself.

#### Scenario: Opening an area

- **WHEN** the user activates AI Tools in the navigation
- **THEN** `/admin/ai` opens, AI Tools' pages are listed, and Employment's pages are not

#### Scenario: A page beneath a page

- **WHEN** the user opens `/admin/resume/applications/new`
- **THEN** Applications is marked as the current page, and Resume Management at `/admin/resume` is not

#### Scenario: A page with only a shorter match

- **WHEN** the user opens `/admin/resume/editor`
- **THEN** Resume Management is marked as the current page and Employment's pages are listed

#### Scenario: The dashboard is not always current

- **WHEN** the user opens `/admin/ai/systems`
- **THEN** the Admin Dashboard is not marked as the current page

#### Scenario: Assistive technology

- **WHEN** a screen reader reaches the marked destination
- **THEN** it is announced as the current page

### Requirement: Admin pages behave as they did before the new shell

Moving to the new shell SHALL NOT change any admin page's address, permission, form, action or data. Back links, flash messages and the log out control SHALL keep working. Pages that deliberately hide the admin navigation for a full-screen session SHALL continue to hide it.

#### Scenario: Existing addresses

- **WHEN** a permitted user requests an admin address that worked before this change
- **THEN** it answers with the same page

#### Scenario: Flash message

- **WHEN** an action redirects back with a success or error flash message
- **THEN** the message is shown and can be dismissed

#### Scenario: Full-screen session pages

- **WHEN** the user opens an application's Discussion page or a Company Intel session page
- **THEN** no admin navigation is shown

#### Scenario: Log out

- **WHEN** the user activates "Log out" in the navigation on a wide screen or in the panel on a narrow screen
- **THEN** the session ends

### Requirement: The admin's look comes from one source and keeps the site's identity

All of the admin's colour, type, spacing, radius and border values SHALL be defined in one token source. Admin code SHALL NOT introduce hard-coded colours, gradients or box shadows, and the linter SHALL report any that are added, apart from named exemptions for code shared with the chat bundle. The admin SHALL keep the site's existing palette and typefaces. It SHALL show visible keyboard focus and respect a reduced-motion preference.

#### Scenario: Changing a token

- **WHEN** a token's value changes
- **THEN** every admin page using that token shows the new value, with no page file edited

#### Scenario: A hard-coded colour is added

- **WHEN** a developer adds a hex colour, a gradient or a box shadow to an admin page
- **THEN** the linter reports an error at that line

#### Scenario: Identity

- **WHEN** an admin page is compared with the public site
- **THEN** both use the same primary and secondary colours and the same heading and body typefaces

#### Scenario: Reduced motion

- **WHEN** a user whose system asks for reduced motion opens the narrow-screen navigation panel
- **THEN** the panel opens without a sliding animation

### Requirement: The public chat is not affected

Redesigning the admin SHALL NOT change the appearance of the chat pages served by the separate chat bundle.

#### Scenario: Chat after the redesign

- **WHEN** a visitor opens a chat page after this change
- **THEN** its colours, typography and component styling match what they were before this change

### Requirement: A design reference shows the system in one place

The system SHALL provide a design reference page at `/admin/design-reference` that shows the design tokens and each shared admin component in its meaningful states, such as loading, empty, populated and disabled. The page SHALL use only built-in sample data, SHALL change nothing when its controls are used, and SHALL NOT be listed in the navigation or on any hub.

#### Scenario: A permitted user

- **WHEN** a user who may open the Admin Dashboard and holds `edit-settings` opens `/admin/design-reference`
- **THEN** the page shows the tokens and shared component states

#### Scenario: A user without permission

- **WHEN** a signed-in user who may open the Admin Dashboard but does not hold `edit-settings` requests `/admin/design-reference`
- **THEN** the request is refused with 403

#### Scenario: An anonymous visitor

- **WHEN** an unauthenticated visitor requests `/admin/design-reference`
- **THEN** they are redirected to login

#### Scenario: Not linked

- **WHEN** a user holding every admin permission opens the Admin Dashboard
- **THEN** no navigation entry or tile links to the design reference

#### Scenario: Sample data only

- **WHEN** the page is opened
- **THEN** it receives no application, resume, user or conversation records from the server
