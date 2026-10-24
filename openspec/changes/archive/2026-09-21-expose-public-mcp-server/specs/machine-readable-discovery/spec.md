## Purpose

Describes the site to machines that arrive without being told what it is — search crawlers, LLM crawlers and agents — so that a visiting agent can identify whose site it is, what they do, and that a structured query endpoint exists, without parsing rendered HTML.

## ADDED Requirements

### Requirement: The site publishes structured identity data

The site's homepage SHALL publish machine-readable structured data identifying the site owner as a person and the page as their profile, covering at minimum their name, professional title, summary, site URL and public profile links. The structured data SHALL be derived from the same source as the rendered page, so that the two cannot describe the owner differently.

#### Scenario: Structured identity is present on the homepage

- **WHEN** a crawler retrieves the homepage
- **THEN** the response carries a structured-data block identifying the owner as a person with their name, title, summary and site URL

#### Scenario: Structured data matches the rendered page

- **WHEN** the owner's title or summary changes at its source
- **THEN** both the rendered page and the structured-data block reflect the change without a separate edit

### Requirement: Structured identity data withholds direct contact details

The published structured data SHALL NOT contain the owner's email address or phone number. Those fields are withheld from anonymous callers of the query endpoint, and publishing them as structured data on an anonymously readable page would defeat that decision.

#### Scenario: No contact details in structured data

- **WHEN** a crawler retrieves the homepage
- **THEN** the structured-data block contains no email address and no phone number

### Requirement: The site states where its query endpoint is

The site SHALL publish a plain-text description of itself for language-model consumers at a conventional well-known filename, and that description SHALL name the query endpoint, state that it requires no credentials for public data, and say what a caller may additionally obtain by presenting a token.

#### Scenario: The description is retrievable

- **WHEN** a client retrieves the site's language-model description file
- **THEN** it is served as plain text and names the query endpoint's URL

#### Scenario: The description explains the access levels

- **WHEN** a client reads that description
- **THEN** it states that public data requires no credentials and that a token yields more

### Requirement: Machine-facing descriptions are not disallowed to crawlers

The site's crawler directives SHALL continue to permit retrieval of the homepage, the language-model description and the query endpoint, so that the descriptions published above are actually reachable by the agents they are written for.

#### Scenario: Crawler directives permit the discovery surfaces

- **WHEN** a crawler consults the site's crawler directives
- **THEN** neither the language-model description nor the query endpoint is disallowed
