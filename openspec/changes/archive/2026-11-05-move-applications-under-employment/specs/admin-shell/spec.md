## MODIFIED Requirements

### Requirement: Navigation shows the current area's pages and marks the current page

Activating an area SHALL open that area's page. The navigation SHALL list the pages of the area containing the current page and SHALL NOT list the pages of any other area. Exactly one destination SHALL be marked as the current page, namely the one whose address most specifically matches the current address. The Admin Dashboard SHALL be marked only on `/admin` itself.

#### Scenario: Opening an area

- **WHEN** the user activates AI Tools in the navigation
- **THEN** `/admin/ai` opens, AI Tools' pages are listed, and Employment's pages are not

#### Scenario: A page beneath a page

- **WHEN** the user opens `/admin/employment/applications/new`
- **THEN** Applications is marked as the current page, and the Employment hub at `/admin/employment` is not

#### Scenario: A page with only a shorter match

- **WHEN** the user opens `/admin/resume/editor`
- **THEN** Resume Management is marked as the current page and Employment's pages are listed

#### Scenario: The dashboard is not always current

- **WHEN** the user opens `/admin/ai/systems`
- **THEN** the Admin Dashboard is not marked as the current page

#### Scenario: Assistive technology

- **WHEN** a screen reader reaches the marked destination
- **THEN** it is announced as the current page
