## Purpose

Records an audit trail and warns the account owner whenever a removal action leaves
them with no remaining method of a given account-security kind (two-factor
authentication or passkeys), so a downgrade in account security is never silent.

## ADDED Requirements

### Requirement: Security downgrade detection is scoped to "last of a kind"
The system SHALL treat a security-method removal as a downgrade only when, immediately
after the removal, the user has zero remaining active methods of that same kind. A
removal SHALL NOT be treated as a downgrade when the user still has at least one other
active method of that same kind remaining.

#### Scenario: Disabling two-factor authentication when it is the only 2FA method
- **WHEN** a user disables two-factor authentication and it was previously confirmed
  and active
- **THEN** the system treats this as a security downgrade, because two-factor
  authentication has no concept of multiple concurrent methods

#### Scenario: Disabling two-factor authentication that was never confirmed
- **WHEN** a user cancels an in-progress two-factor authentication setup (a secret was
  generated but never confirmed) and the setup is discarded
- **THEN** the system does NOT treat this as a security downgrade, because
  two-factor authentication was never actually active for that user

#### Scenario: Removing a passkey when another passkey remains
- **WHEN** a user removes one of their registered passkeys and at least one other
  passkey remains registered to their account
- **THEN** the system does NOT treat this as a security downgrade

#### Scenario: Removing the last remaining passkey
- **WHEN** a user removes a passkey and, immediately after removal, they have zero
  passkeys registered to their account
- **THEN** the system treats this as a security downgrade

### Requirement: Security downgrades are recorded in an audit trail
Whenever a security downgrade (as defined above) occurs, the system SHALL record an
audit entry identifying the affected user, which kind of method was removed (two-factor
authentication or passkey), and when the removal occurred.

#### Scenario: Audit entry created for a last-2FA disable
- **WHEN** a user's only active two-factor authentication method is disabled
- **THEN** an audit entry is recorded identifying the user, the method kind
  ("two-factor authentication"), and a timestamp

#### Scenario: Audit entry created for a last-passkey removal
- **WHEN** a user's last remaining passkey is removed
- **THEN** an audit entry is recorded identifying the user, the method kind
  ("passkey"), and a timestamp

#### Scenario: No audit entry when other methods remain
- **WHEN** a removal occurs but the user still has another active method of that same
  kind
- **THEN** no audit entry is recorded for that removal

### Requirement: The affected user is alerted of a security downgrade
Whenever a security downgrade occurs, the system SHALL send the affected user an email
notification informing them that a security method was removed from their account and
that they no longer have an active method of that kind.

#### Scenario: User is emailed after their last 2FA method is disabled
- **WHEN** a user's only active two-factor authentication method is disabled
- **THEN** the system queues an email to that user's registered email address warning
  them that two-factor authentication was removed from their account

#### Scenario: User is emailed after their last passkey is removed
- **WHEN** a user's last remaining passkey is removed
- **THEN** the system queues an email to that user's registered email address warning
  them that passkey authentication was removed from their account

#### Scenario: No alert when other methods remain
- **WHEN** a removal occurs but the user still has another active method of that same
  kind
- **THEN** no downgrade alert email is sent for that removal

### Requirement: Detection and side effects do not alter the removal action's outcome
The detection of a security downgrade and its resulting audit entry or alert email
SHALL NOT block, delay the visible response of, or change the success/failure outcome
of the underlying 2FA-disable or passkey-removal action. Side effects SHALL be
processed asynchronously.

#### Scenario: Passkey removal succeeds even if downgrade processing fails
- **WHEN** a user removes their last passkey and the audit-log or alert-email
  processing subsequently fails
- **THEN** the passkey removal itself is still considered successful and the user
  receives the normal removal confirmation
