## Purpose

Lets any external MCP client query the site's resume, blog and site-profile data without an account, while an optional bearer token raises the caller's identity so that the same tools disclose the privileged fields that identity is entitled to.

## ADDED Requirements

### Requirement: A public MCP endpoint answers unauthenticated requests

The system SHALL expose a single MCP endpoint over HTTP that completes the MCP initialization handshake, tool listing, and tool invocation for a caller presenting no credentials. An anonymous caller SHALL NOT receive an authentication challenge or an authorization error for any tool on the roster.

#### Scenario: Anonymous client completes a session

- **WHEN** an MCP client connects to the endpoint with no `Authorization` header and initializes a session
- **THEN** the handshake succeeds and the server reports its tool capability

#### Scenario: Anonymous client lists and calls a tool

- **WHEN** an anonymous client lists tools and then invokes one from the roster
- **THEN** the call returns a successful structured result, not an authentication error

### Requirement: The endpoint advertises exactly one curated read-only roster

The endpoint SHALL advertise exactly these tools: resume data, recent blog posts, and site profile information. Every tool reachable through this endpoint SHALL be read-only — no tool reachable here may create, modify, approve, or delete any stored data. Tools that exist elsewhere in the system for editing resume data, reviewing resume edit candidates, or working on targeted resumes and cover letters SHALL NOT be reachable through this endpoint.

#### Scenario: Roster contains only the three read tools

- **WHEN** any client lists the endpoint's tools
- **THEN** the response contains the resume-data, recent-blog-posts and site-info tools and no others

#### Scenario: A write tool is not reachable by name

- **WHEN** a client invokes a resume-editing or candidate-approval tool name against this endpoint
- **AND** the caller holds a token whose user has every permission those tools require
- **THEN** the endpoint reports the tool as unknown and no data is changed

### Requirement: The system can issue API tokens for its users

The system SHALL be able to issue a bearer API token for any of its users and store it, so that the token tier below is reachable at all. Issuing a token for an existing user SHALL succeed regardless of the form of that user's identifier.

#### Scenario: A token can be issued for a user

- **WHEN** an API token is issued for an existing user
- **THEN** the token is persisted and returned to the issuer without error

#### Scenario: An issued token authenticates its owner

- **WHEN** a token issued for a user is presented on a call to the endpoint
- **THEN** the call is authenticated as that user

### Requirement: A valid bearer token raises the caller's identity

When a request carries a valid API bearer token, the system SHALL resolve it to the owning user and evaluate every identity-dependent disclosure decision inside the tools against that user's permissions, exactly as the same tool would behave for that user elsewhere in the system.

#### Scenario: Token holder with salary permission sees salary history

- **WHEN** a client calls the resume-data tool with a bearer token belonging to a user who holds the salary-viewing permission
- **THEN** the returned experience entries include their salary start and end values

#### Scenario: Token holder without salary permission does not

- **WHEN** a client calls the resume-data tool with a bearer token belonging to a user who lacks the salary-viewing permission
- **THEN** the returned experience entries carry no salary values

### Requirement: An anonymous caller receives the public projection

An anonymous caller SHALL receive resume content with withheld fields emptied rather than an error, and the shape of the response SHALL NOT change — a consumer reads the same structure whether or not it is authenticated. The anonymous projection SHALL withhold salary history and SHALL withhold the direct contact fields (email address and phone number), while retaining the public profile link and site URL as contact paths. Education, experience, skills and projects SHALL be included for an anonymous caller.

#### Scenario: Anonymous resume read withholds salary

- **WHEN** an anonymous client calls the resume-data tool
- **THEN** the response contains the full experience list with salary values withheld
- **AND** the response is a success, not an error

#### Scenario: Anonymous resume read withholds direct contact details

- **WHEN** an anonymous client calls the resume-data tool
- **THEN** the personal section carries no email address and no phone number
- **AND** it still carries the name, title, summary, public profile link and site URL

#### Scenario: Anonymous resume read includes education and experience

- **WHEN** an anonymous client calls the resume-data tool
- **THEN** the response includes the education, experience, skills and projects sections

#### Scenario: Token holder receives direct contact details

- **WHEN** a client calls the resume-data tool with a valid bearer token
- **THEN** the personal section includes the email address and phone number

### Requirement: Contact redaction is a property of this endpoint only

Withholding direct contact details SHALL apply to callers of this endpoint and SHALL NOT change what the same underlying tool discloses on other paths. An anonymous visitor using the site's public chat bots SHALL continue to receive the contact fields as before.

#### Scenario: Public chat bot still discloses contact details

- **WHEN** an unauthenticated visitor's conversation with a public chat bot invokes the resume-data tool
- **THEN** the tool's result still carries the email address and phone number

### Requirement: The site-profile tool publishes only already-public content

The site-profile tool SHALL return only content already rendered on the public site, and SHALL enumerate the keys it returns explicitly rather than passing through whatever the site configuration happens to contain. Navigation entries, which include administrative destinations, SHALL NOT be returned.

#### Scenario: Site-profile response is limited to public content

- **WHEN** any client calls the site-profile tool
- **THEN** the response contains only the site title, the projects list and the interests list

#### Scenario: Navigation and administrative links are not returned

- **WHEN** any client calls the site-profile tool
- **THEN** the response contains no navigation link list and no administrative destinations

### Requirement: Unpublished resume drafts are never disclosed without edit permission

The system SHALL NOT disclose the existence or contents of a pending AI-drafted resume revision to a caller who does not hold the resume-edit permission. A caller without that permission SHALL receive no pending-revision indicator, and a request from such a caller to load a specific revision SHALL return the live published resume instead of the draft, without confirming whether that revision exists.

#### Scenario: Anonymous caller is not told a draft exists

- **WHEN** an anonymous client calls the resume-data tool
- **AND** a pending resume edit candidate exists for the live resume version
- **THEN** the response carries no pending-revision indicator

#### Scenario: Anonymous caller requesting a revision gets the live resume

- **WHEN** an anonymous client calls the resume-data tool asking for a specific revision number
- **THEN** the response contains the live published resume data
- **AND** the response does not reveal whether a candidate with that revision number exists

#### Scenario: Permitted caller still sees drafts

- **WHEN** a client calls the resume-data tool with a bearer token belonging to a user holding the resume-edit permission
- **AND** a pending candidate exists for the live resume version
- **THEN** the response reports the pending revision number
- **AND** asking for that revision number returns the draft snapshot

#### Scenario: Anonymous chat visitor is not told a draft exists

- **WHEN** an unauthenticated visitor's chat conversation invokes the resume-data tool
- **AND** a pending resume edit candidate exists
- **THEN** the tool reports no pending revision number

### Requirement: An invalid bearer token is rejected rather than downgraded

When a request carries an `Authorization` bearer credential that does not resolve to a valid, unrevoked token, the system SHALL reject the request with an authentication error rather than serving it anonymously. A caller whose token was revoked SHALL learn that, instead of silently receiving a reduced response it may mistake for complete data.

#### Scenario: Revoked token is rejected

- **WHEN** a client calls the endpoint with a bearer token that has been revoked or deleted
- **THEN** the request fails with an authentication error and returns no tool data

#### Scenario: Malformed credential is rejected

- **WHEN** a client calls the endpoint with an `Authorization` header that is not a resolvable token
- **THEN** the request fails with an authentication error

### Requirement: The endpoint is rate limited per caller in tiers

The endpoint SHALL apply rate limits keyed on the caller — the real client address behind the site's reverse proxy for an anonymous caller, and the presented token for an authenticated one. Each tier SHALL enforce both a short burst window and a longer sustained window, so that a caller may complete an ordinary session freely while systematic harvesting is bounded. A token holder SHALL receive a materially higher allowance than an anonymous caller. A caller exceeding a limit SHALL receive a throttling response and SHALL NOT be able to exhaust any other caller's allowance.

#### Scenario: An ordinary session is never throttled

- **WHEN** a client performs a complete session — initialize, list tools, and call every tool on the roster
- **THEN** no request in that session is throttled

#### Scenario: Excess requests are throttled

- **WHEN** a single caller exceeds either the burst or the sustained limit for its tier
- **THEN** further requests from that caller receive a throttling response until that window resets

#### Scenario: Throttling is per caller

- **WHEN** one caller has been throttled
- **THEN** a request from a different client address is still served

#### Scenario: A token holder gets the higher tier

- **WHEN** a caller presents a valid bearer token
- **THEN** its allowance is the token tier's, keyed on the token rather than the client address

### Requirement: Tool results are served from cache

Because the underlying resume, blog and site data change on the order of weeks, the endpoint SHALL serve repeated identical tool calls from a cache rather than recomputing them, keyed so that callers at different disclosure levels never receive each other's projection. A cached result SHALL be invalidated when its underlying data changes, so that a caller never receives a stale resume after a new version is published or a stale post list after a post is published.

#### Scenario: Repeated identical calls do not recompute

- **WHEN** the same tool is called twice with the same arguments at the same disclosure level
- **THEN** the second call is served from cache

#### Scenario: Anonymous and authenticated results are cached separately

- **WHEN** an anonymous caller and a token holder call the same tool with the same arguments
- **THEN** each receives its own projection and neither is served the other's cached result

#### Scenario: Publishing a new resume version invalidates the cache

- **WHEN** a new resume version is published
- **THEN** the next resume-data call returns the new version rather than a cached earlier one

#### Scenario: Publishing a post invalidates the post list

- **WHEN** a blog post is published
- **THEN** the next recent-posts call reflects it

### Requirement: Every call is recorded

The system SHALL record every request to the endpoint, whether it succeeds, fails, or is rejected before reaching a tool. Each record SHALL carry the time, the protocol method, the tool name where one applies, the session identifier, the resolved identity or an explicit indication of anonymity, the Cloudflare-aware client address, the user agent, the outcome, and the duration. Records SHALL NOT contain credentials.

#### Scenario: An anonymous tool call is recorded

- **WHEN** an anonymous client calls a tool
- **THEN** a record is written carrying the method, tool name, session identifier, client address, outcome and duration, and marking the caller as anonymous

#### Scenario: An authenticated call records the identity

- **WHEN** a token holder calls a tool
- **THEN** the record identifies the resolved user and carries no part of the token itself

#### Scenario: A rejected call is still recorded

- **WHEN** a request is rejected for an invalid token or by the rate limiter
- **THEN** a record is written with that outcome

### Requirement: The calling agent identifies itself in the record

The system SHALL capture the calling agent's self-reported name and version from the protocol's initialization handshake and associate it with that session's records, so that traffic can be attributed to the kind of client producing it. A client that reports nothing SHALL be recorded as unidentified rather than causing an error.

#### Scenario: A client's reported name is captured

- **WHEN** a client initializes a session reporting its name and version
- **THEN** those values are stored against that session

#### Scenario: Later calls in the session are attributable

- **WHEN** that same client subsequently calls a tool
- **THEN** its record can be attributed to the name and version captured at initialization

#### Scenario: A client reporting nothing is tolerated

- **WHEN** a client initializes without reporting its identity
- **THEN** the session is recorded as unidentified and the handshake still succeeds

### Requirement: Call records are retained for a bounded period

Call records SHALL be swept on a schedule so that the log does not grow without bound, and the retention period SHALL be configurable.

#### Scenario: Records older than the retention period are removed

- **WHEN** the sweep runs
- **THEN** records older than the configured retention period are deleted and newer ones are kept
