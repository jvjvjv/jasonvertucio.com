## Purpose

Lets a person sign in to the site with their bspdx.com identity, creating or linking a local account on first use, and makes that the replacement for password sign-in.

## ADDED Requirements

### Requirement: A visitor can sign in through bspdx.com
The system SHALL offer sign-in through bspdx.com using an authorization-code flow with proof-of-possession protection and anti-forgery state. A successful, validated sign-in SHALL establish an ordinary authenticated session for the matching local user.

#### Scenario: Successful sign-in
- **WHEN** a visitor chooses to sign in with bspdx.com and approves access there
- **THEN** they return to the site signed in as the matching local user

#### Scenario: The returned state does not match
- **WHEN** the callback arrives with a state or nonce that does not match the one issued for that browser session
- **THEN** no session is created and the visitor is returned to the login page with a generic error

#### Scenario: The provider denies or is unreachable
- **WHEN** the visitor declines access, or the provider cannot be reached or returns an error
- **THEN** no session is created and the visitor is returned to the login page with a generic error

### Requirement: The identity token is validated before it is trusted
The system SHALL accept an identity only from a token whose signature verifies against the provider's published keys, and whose issuer, audience, expiry and nonce are correct.

#### Scenario: A forged or expired token
- **WHEN** the callback yields a token with a bad signature, wrong issuer or audience, or an expired time
- **THEN** no session is created and no account is created or linked

### Requirement: A first sign-in creates a local account with no roles
When a bspdx.com identity has no linked local user and no existing local user is eligible for linking, the system SHALL create a new local user from the identity's claims and link it. The new user SHALL hold no roles or permissions.

#### Scenario: A new identity signs in
- **WHEN** a bspdx.com identity with a verified email that matches no local user signs in for the first time
- **THEN** a local user is created with that email, holding no roles, and the visitor is signed in

#### Scenario: A new account has no elevated access
- **WHEN** an automatically created user attempts to open an administrative page
- **THEN** access is refused

### Requirement: A returning identity is matched by its stable subject
Once an identity is linked, later sign-ins SHALL match the local user by the provider's stable subject identifier and SHALL NOT depend on the email claim.

#### Scenario: The email changes at the provider
- **WHEN** a linked identity signs in with a different email than when it was linked
- **THEN** the visitor is signed in as the same local user and no second account is created

### Requirement: An existing user is linked only by a verified email
When an unlinked identity presents an email that matches an existing local user, the system SHALL link to that user only if the provider asserts the email is verified. Otherwise the system SHALL refuse the sign-in and SHALL NOT create a duplicate account or link to the existing one.

#### Scenario: Verified email matches an existing user
- **WHEN** an unlinked identity with a verified email equal to an existing user's email signs in
- **THEN** it is linked to that existing user, who is signed in with their existing roles

#### Scenario: Unverified email matches an existing user
- **WHEN** an unlinked identity with an unverified email equal to an existing user's email signs in
- **THEN** no session is created, no link is made, and no account is created

#### Scenario: No usable email
- **WHEN** the identity carries no email
- **THEN** no session is created and no account is created

### Requirement: Password sign-in is replaced
When federated login is enabled, the system SHALL NOT accept email-and-password sign-in or password-based self-registration. Sign-in by passkey or by authenticator code for users who have enabled those methods SHALL continue to work.

#### Scenario: A password sign-in attempt
- **WHEN** a client submits an email and password to the sign-in endpoint while federated login is enabled
- **THEN** no session is created

#### Scenario: A passkey user signs in
- **WHEN** a user who has a registered passkey signs in with it while federated login is enabled
- **THEN** they are signed in as before

#### Scenario: Federated login is disabled by configuration
- **WHEN** federated login is turned off in configuration
- **THEN** the login page and password sign-in behave as they did before this capability existed

### Requirement: Provider settings and secrets come from configuration
The provider's issuer, client credentials and redirect address SHALL be read from application configuration. The client secret SHALL NOT be present in source control or rendered to any page.

#### Scenario: Provider is not configured
- **WHEN** federated login is enabled but the issuer or client credentials are missing
- **THEN** the sign-in option is not offered and a sign-in attempt does not proceed
