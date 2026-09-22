## ADDED Requirements

### Requirement: Machine-facing descriptions reference the human-readable page

The site's machine-facing descriptions SHALL reference the page describing its AI work and query endpoint, so that an agent reading them can direct a person to an explanation rather than only to a protocol endpoint. The structured identity data SHALL relate the owner to that page, and the language-model description SHALL name it alongside the endpoint.

#### Scenario: The language-model description names the page

- **WHEN** a client retrieves the site's language-model description
- **THEN** it names the human-readable page's URL alongside the query endpoint's

#### Scenario: Structured data relates the owner to the page

- **WHEN** a crawler retrieves the homepage
- **THEN** the structured identity data references the page as a related page for the owner
