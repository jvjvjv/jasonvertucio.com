## Purpose

Defines how a signed-in session is ended, so that only the user's own deliberate
action can sign them out.

## ADDED Requirements

### Requirement: Logging out requires a forgery-protected POST

A session SHALL be ended only by a POST request carrying a valid forgery token.
A GET request to the logout address SHALL NOT end the session.

#### Scenario: Using the navigation's log out control

- **WHEN** a signed-in user activates "Log out" in the navigation
- **THEN** the session ends and the user is taken to the homepage

#### Scenario: A GET request to the logout address

- **WHEN** a signed-in user's browser requests the logout address with GET, for example from an image tag on another site
- **THEN** the user is still signed in afterwards

#### Scenario: A POST without a token

- **WHEN** a logout POST arrives without a valid forgery token
- **THEN** it is refused and the session continues

### Requirement: The log out control is a button available wherever the navigation is

The log out control SHALL be shown to signed-in users in every navigation that
lists account destinations — public site, admin and chat — SHALL be operable by
keyboard, and SHALL NOT be shown to anonymous visitors.

#### Scenario: Admin navigation

- **WHEN** a signed-in administrator opens the admin navigation
- **THEN** "Log out" is present and activating it ends the session

#### Scenario: Anonymous visitor

- **WHEN** an anonymous visitor views the navigation
- **THEN** no log out control is shown
