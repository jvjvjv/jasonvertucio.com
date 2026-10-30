# federated-login Specification

## Purpose

Makes bspdx.com the only way to sign in to the site: a person signs in with their bspdx.com identity, which creates a local account on first use or resolves to an account the site owner linked explicitly.

## Requirements

### Requirement: A visitor can sign in through bspdx.com
The system SHALL offer sign-in through bspdx.com using an authorization-code flow with proof-of-possession protection and anti-forgery state. A successful sign-in SHALL establish an ordinary authenticated session for the resolved local user.

#### Scenario: Successful sign-in
- **WHEN** a visitor chooses to sign in with bspdx.com and approves access there
- **THEN** they return to the site signed in as the resolved local user

#### Scenario: The returned state does not match
- **WHEN** the callback arrives with a state that does not match the one issued for that browser session
- **THEN** no session is created and the visitor is returned to the login page with a generic error

#### Scenario: The provider denies or is unreachable
- **WHEN** the visitor declines access, or the provider cannot be reached or returns an error
- **THEN** no session is created and the visitor is returned to the login page with a generic error

### Requirement: The identity comes only from the provider's userinfo endpoint
The system SHALL obtain the identity by exchanging the authorization code, together with the PKCE verifier issued for that browser session, at the provider's token endpoint, and then requesting the provider's userinfo endpoint server-to-server with the access token from that exchange. The system SHALL NOT accept an access token or identity data supplied by the browser. An identity SHALL be accepted only if the userinfo response succeeds and carries both a subject identifier and an email address.

#### Scenario: The code exchange fails
- **WHEN** the provider rejects the authorization code or the PKCE verifier at the token endpoint
- **THEN** no session is created and no account is created or linked

#### Scenario: The userinfo response is unusable
- **WHEN** the userinfo request fails, or its response is not valid, or it carries no subject identifier or no email
- **THEN** no session is created and no account is created or linked

### Requirement: Calls to the provider are made only over trusted connections
The system SHALL contact the provider only at its configured HTTPS address, SHALL NOT follow redirects on the token or userinfo calls, and SHALL give up on a call that does not answer within a short timeout.

#### Scenario: The provider answers with a redirect
- **WHEN** the token or userinfo call receives a redirect response
- **THEN** the redirect is not followed and the sign-in fails with no session

#### Scenario: The configured address is not HTTPS
- **WHEN** the provider's base address is configured without HTTPS
- **THEN** the sign-in option is not offered and a sign-in attempt does not proceed

### Requirement: A first sign-in creates a local account with no roles
When a bspdx.com identity is not linked to a local user and its email belongs to no local user, the system SHALL create a new local user from the identity's claims and link it. The new user SHALL hold no roles or permissions.

#### Scenario: A new identity signs in
- **WHEN** a bspdx.com identity whose email matches no local user signs in for the first time
- **THEN** a local user is created with that email, holding no roles, and the visitor is signed in

#### Scenario: A new account has no elevated access
- **WHEN** an automatically created user attempts to open an administrative page
- **THEN** access is refused

### Requirement: A returning identity is matched by its stable subject
Once an identity is linked, later sign-ins SHALL match the local user by the provider's stable subject identifier and SHALL NOT depend on the email.

#### Scenario: The email changes at the provider
- **WHEN** a linked identity signs in with a different email than its local user has, and no other local user holds that email
- **THEN** the visitor is signed in as the same local user, whose email is updated, and no second account is created

#### Scenario: The new email belongs to another local user
- **WHEN** a linked identity signs in with an email that another local user already holds
- **THEN** the visitor is signed in as their linked user and neither user's email changes

### Requirement: Identities are never linked to existing users automatically
The system SHALL NOT attach a bspdx.com identity to an existing local user by matching email or any other claim. When an unlinked identity's email belongs to an existing local user, the system SHALL refuse the sign-in, SHALL NOT create an account, and SHALL NOT link.

#### Scenario: Unlinked identity with an existing local email
- **WHEN** an unlinked bspdx.com identity signs in with an email that an existing local user holds
- **THEN** no session is created, no account is created, no link is made, and the visitor is told to contact the site owner

### Requirement: The site owner can link an existing user explicitly
The system SHALL provide an operator command that links an existing local user, identified by email, to a bspdx.com subject identifier. The command SHALL refuse when the local user is already linked or the subject is already linked to another user.

#### Scenario: Linking an existing user
- **WHEN** the operator links an unlinked local user to an unlinked bspdx.com subject
- **THEN** a later sign-in by that subject signs in as that existing user with their existing roles

#### Scenario: Linking an already-linked user or subject
- **WHEN** the operator links a user who is already linked, or a subject that is already linked
- **THEN** the command refuses and no link changes

### Requirement: bspdx.com is the only sign-in method
The system SHALL NOT authenticate anyone by password, passkey, or authenticator code, and SHALL NOT offer password registration, password reset, password change, local two-factor enrollment or challenge, or passkey management. Signing out SHALL continue to work as specified by session logout.

#### Scenario: A password sign-in attempt
- **WHEN** a client submits an email and password to the former sign-in address
- **THEN** no session is created

#### Scenario: A passkey or authenticator-code sign-in attempt
- **WHEN** a client attempts to sign in with a passkey or an authenticator code
- **THEN** no session is created

#### Scenario: The login page
- **WHEN** a guest opens the login page
- **THEN** it offers only sign-in with bspdx.com and asks for no email or password

#### Scenario: The profile page
- **WHEN** a signed-in user opens their profile
- **THEN** it shows their linked bspdx.com identity and offers no password, two-factor or passkey controls

### Requirement: Provider settings and secrets come from configuration
The provider's base address, client credentials and redirect address SHALL be read from application configuration. The client secret SHALL NOT be present in source control or rendered to any page.

#### Scenario: Provider is not configured
- **WHEN** the provider's base address or client credentials are missing
- **THEN** the sign-in option is not offered and a sign-in attempt does not proceed

### Requirement: Sign-in attempts are rate limited per client address
The system SHALL limit federated sign-in redirects and callbacks per real client address behind the site's reverse proxy.

#### Scenario: Too many attempts
- **WHEN** one client address exceeds the federated sign-in limit
- **THEN** further attempts are refused with a throttling response until the window passes
