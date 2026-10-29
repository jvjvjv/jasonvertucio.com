## REMOVED Requirements

### Requirement: Security downgrade detection is scoped to "last of a kind"
**Reason**: Passkeys and local two-factor authentication are removed; bspdx.com is the only sign-in method and owns all MFA, so there is no local security method left to remove.
**Migration**: None. Existing `security_audit_log_entries` rows are kept as history. Security changes are now bspdx.com's responsibility.

### Requirement: Security downgrades are recorded in an audit trail
**Reason**: Passkeys and local two-factor authentication are removed; bspdx.com is the only sign-in method and owns all MFA, so there is no local security method left to remove.
**Migration**: None. Existing `security_audit_log_entries` rows are kept as history. Security changes are now bspdx.com's responsibility.

### Requirement: The affected user is alerted of a security downgrade
**Reason**: Passkeys and local two-factor authentication are removed; bspdx.com is the only sign-in method and owns all MFA, so there is no local security method left to remove.
**Migration**: None. Existing `security_audit_log_entries` rows are kept as history. Security changes are now bspdx.com's responsibility.

### Requirement: Detection and side effects do not alter the removal action's outcome
**Reason**: Passkeys and local two-factor authentication are removed; bspdx.com is the only sign-in method and owns all MFA, so there is no local security method left to remove.
**Migration**: None. Existing `security_audit_log_entries` rows are kept as history. Security changes are now bspdx.com's responsibility.
