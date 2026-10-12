## Purpose

Guarantees that a credential stored for an AI system — a provider key, service
credentials, or a header secret in a web-tool policy — is never sent back to a
browser once saved.

## Requirements

### Requirement: Stored AI system secrets are never sent to the browser

No admin page or admin endpoint SHALL include the value of a stored provider
key, stored service credentials, or a stored web-tool header secret in its
response. A page SHALL state only whether each is set.

#### Scenario: The AI systems list

- **WHEN** an administrator opens the AI systems list
- **THEN** the page's data contains no provider key, credentials or header secret for any system

#### Scenario: The edit page

- **WHEN** an administrator opens an AI system that has a stored provider key
- **THEN** the key field is empty, the page indicates a key is set, and the page's data does not contain the key

#### Scenario: The logs page

- **WHEN** an administrator opens an AI system's logs
- **THEN** the page's data contains no stored secret for that system

### Requirement: Leaving a secret field untouched keeps the stored value

Saving an AI system without entering a value in a secret field SHALL keep the
stored secret. Entering a value SHALL replace it. Removing a stored secret SHALL
be an explicit action.

#### Scenario: Editing another field

- **WHEN** an administrator changes a system's name and saves without touching the key field
- **THEN** the stored key is unchanged

#### Scenario: Replacing a key

- **WHEN** an administrator enters a new key and saves
- **THEN** the new key is stored and the old one is gone

#### Scenario: Clearing a key

- **WHEN** an administrator chooses to remove the stored key and saves
- **THEN** the system has no stored key

#### Scenario: Web-tool policy with a stored header secret

- **WHEN** an administrator adds an allowed domain to a policy that already holds a header secret for another domain, without re-entering that secret
- **THEN** the existing header secret is still stored after saving

### Requirement: Model lookup works without resending the stored key

Fetching the model list for an existing AI system SHALL use that system's stored
key when the administrator has not entered a new one.

#### Scenario: Refreshing models on an existing system

- **WHEN** an administrator refreshes the model list on a system with a stored key, leaving the key field empty
- **THEN** the model list is returned

#### Scenario: Looking up models for another system's key

- **WHEN** a model lookup names a system the caller may not manage
- **THEN** the lookup is refused and no stored key is used

### Requirement: Duplicating a system does not expose its secrets

Duplicating an AI system SHALL carry its stored secrets to the copy on the
server, without the values passing through the browser.

#### Scenario: Duplicating a system with a key

- **WHEN** an administrator duplicates a system that has a stored key
- **THEN** the copy works with the same key, and no response during duplication contains it
