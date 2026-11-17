## MODIFIED Requirements

### Requirement: Company intel feature default

The AI system form SHALL offer `company-intel` as a feature default alongside `targeted-resume`, `cover-letter` and `application-follow-up`. At most one AI system SHALL be the `company-intel` default at a time; making a system the default removes the default from any other.

#### Scenario: Assigning the default

- **WHEN** the admin saves an AI system with the `company-intel` feature default checked
- **THEN** that system becomes the `company-intel` default and no other system remains one

#### Scenario: Unknown feature refused

- **WHEN** a request saves an AI system with a feature default other than `targeted-resume`, `cover-letter`, `company-intel`, `application-follow-up`, `voice:dictation` or `voice:speech`
- **THEN** the request fails validation
