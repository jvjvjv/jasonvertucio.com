## Purpose

Gives the site's AI work a public home that a person can read, and gives the public query endpoint the human-readable explanation it otherwise lacks, with its tool documentation derived from the server so the two cannot disagree.

## ADDED Requirements

### Requirement: A public page presents the owner's AI work

The site SHALL serve a public, unauthenticated page describing the owner's AI work. The page SHALL be reachable at a stable path suitable for citing from external descriptions of the site, and SHALL be rendered in the site's normal layout.

#### Scenario: The page is publicly reachable

- **WHEN** an unauthenticated visitor requests the page
- **THEN** it is served successfully in the site's normal layout

#### Scenario: The page survives being cited

- **WHEN** an external description of the site references the page's path
- **THEN** that path resolves to the page and is not a redirect target that may change

### Requirement: The narrative is editable as a document

The page's prose SHALL be stored as a Markdown document rather than embedded in a template, so that it can be revised without changing application code. Rendering SHALL strip embedded HTML and SHALL NOT permit unsafe links, consistent with how the site renders its other document-backed pages.

#### Scenario: Editing the document changes the page

- **WHEN** the Markdown document is edited
- **THEN** the rendered page reflects the change with no code change

#### Scenario: Embedded markup is not rendered

- **WHEN** the document contains raw HTML or an unsafe link
- **THEN** the rendered page does not emit it

#### Scenario: A missing document does not produce a server error

- **WHEN** the document is absent
- **THEN** the request produces a not-found response rather than an unhandled error

### Requirement: Endpoint documentation is generated from the server's tool roster

The page SHALL document the public query endpoint using the tool roster registered on the server itself — each tool's name, description and accepted arguments — rather than a hand-maintained copy. Adding, removing or re-describing a tool SHALL change the page without any edit to the page or its document.

#### Scenario: Every registered tool is documented

- **WHEN** a visitor reads the page
- **THEN** it lists each tool the endpoint advertises, with that tool's name, description and accepted arguments

#### Scenario: A tool added later documents itself

- **WHEN** a tool is added to the endpoint's roster
- **THEN** the page documents it with no edit to the page or its narrative document

#### Scenario: A tool removed from the roster disappears

- **WHEN** a tool is removed from the endpoint's roster
- **THEN** the page no longer documents it

### Requirement: The page states how to reach the endpoint and what each access level yields

The page SHALL state the endpoint's URL and transport, that public data requires no credentials, what a caller additionally receives by presenting a token, and how to request one. It SHALL NOT expose any credential or a means of issuing one.

#### Scenario: Access levels are explained

- **WHEN** a visitor reads the endpoint documentation
- **THEN** it states the endpoint URL, that no credentials are needed for public data, and what a token additionally yields

#### Scenario: Token issuance is a request, not a self-service flow

- **WHEN** a visitor wants a token
- **THEN** the page tells them how to ask and offers no automated issuance

#### Scenario: No credential appears on the page

- **WHEN** the page is rendered
- **THEN** it contains no token or other credential
