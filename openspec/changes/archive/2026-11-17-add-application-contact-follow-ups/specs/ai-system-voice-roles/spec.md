## MODIFIED Requirements

### Requirement: Voice feature defaults match the system's role
Feature defaults SHALL include `voice:dictation` and `voice:speech`. Only a `transcription` system SHALL be assignable as the `voice:dictation` default, only an `audio` system as the `voice:speech` default, and only a `chat` system as the default for a chat feature (`targeted-resume`, `cover-letter`, `company-intel`, `application-follow-up`).

#### Scenario: Assigning a voice default
- **WHEN** an administrator saves a `transcription` system with the `voice:dictation` default
- **THEN** that system becomes the dictation default, replacing any previous one

#### Scenario: Mismatched default is rejected
- **WHEN** an administrator saves an `audio` system with the `targeted-resume` default
- **THEN** validation fails and no default changes

#### Scenario: Follow-up default must be a chat system
- **WHEN** an administrator saves a `transcription` system with the `application-follow-up` default
- **THEN** validation fails and no default changes
