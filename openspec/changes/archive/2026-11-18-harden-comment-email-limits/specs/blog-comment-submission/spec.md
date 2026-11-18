## ADDED Requirements

### Requirement: A commenter's address must name its mailbox plainly
A submitted comment email address SHALL be refused unless it is a valid address that raises no RFC 5322 warnings. Addresses whose mailbox is hidden by syntax that mail systems accept but people do not write (a quoted local part, a comment in parentheses, or an ideographic full stop in the domain) SHALL be refused with a validation error, and no comment SHALL be created. Ordinary addresses, including `+tag`, dotted and internationalised-domain addresses, SHALL be accepted.

#### Scenario: A quoted local part is refused
- **WHEN** an anonymous visitor submits a comment with the email `"vic.tim"@gmail.com`
- **THEN** the submission is rejected with an email validation error and no comment row is created

#### Scenario: A comment in the address is refused
- **WHEN** an anonymous visitor submits a comment with the email `victim(c)@gmail.com`
- **THEN** the submission is rejected with an email validation error and no comment row is created

#### Scenario: Ordinary addresses are accepted
- **WHEN** an anonymous visitor submits a comment with `first.last+blog@gmail.com` or `name@bücher.de`
- **THEN** the comment is created

## MODIFIED Requirements

### Requirement: Submissions are resisted with the site's existing abuse infrastructure
The comment form SHALL include a honeypot field that legitimate submissions leave empty, and submissions SHALL be rate limited per client address. Every comment SHALL record the submitting `ip_address` and `user_agent`, resolved through the same Cloudflare-aware logic as `IpMiddleware`, so that a later spam determination can inform an `IpBan`. The Cloudflare forwarding header SHALL be used for `ip_address` only when it holds a valid IP address; an empty or malformed header SHALL fall back to the connecting address.

#### Scenario: A honeypot submission is silently discarded
- **WHEN** a submission arrives with a non-empty honeypot field
- **THEN** no comment row is created and the response is indistinguishable from a successful submission

#### Scenario: Rapid repeat submissions are throttled
- **WHEN** the same client address exceeds the configured submission rate
- **THEN** further submissions are rejected with HTTP 429 and no comment rows are created

#### Scenario: Client address is captured behind Cloudflare
- **WHEN** a submission arrives carrying a Cloudflare forwarding header
- **THEN** `ip_address` records the originating client address rather than the proxy's

#### Scenario: A malformed forwarding header is ignored
- **WHEN** a submission arrives with a Cloudflare forwarding header that is empty or not an IP address
- **THEN** `ip_address` records the connecting address
