## Purpose

Defines how an administrator creates and maintains AiSystems that serve speech-to-text or text-to-speech rather than chat, and keeps those voice systems out of every place that selects a chat model.

## ADDED Requirements

### Requirement: An AiSystem is created with a role
The AI system create form SHALL offer a role of `chat`, `audio` (text-to-speech) or `transcription` (speech-to-text), defaulting to `chat`. The role SHALL be locked after creation under the same rule as Provider: editable only while the system is a pending duplicate.

#### Scenario: Creating a voice system
- **WHEN** an administrator creates an AiSystem with role `transcription`
- **THEN** the system is stored with role `transcription`

#### Scenario: Role is locked on an established system
- **WHEN** an administrator opens the Edit page of an AiSystem that is not a pending duplicate
- **THEN** the role field is disabled
- **AND** an update request carrying a different role does not change the stored role

#### Scenario: Existing systems keep working
- **WHEN** an AiSystem created before this change is listed or edited
- **THEN** it is treated as role `chat`

### Requirement: Provider choices depend on the role
The form SHALL offer only providers that can serve the selected role, and the server SHALL reject a provider that cannot serve the submitted role, with an error naming both. `elevenlabs` SHALL be available for the `audio` and `transcription` roles and not for `chat`.

#### Scenario: Unsupported pairing is rejected
- **WHEN** an AiSystem is submitted with provider `lm-studio` and role `audio`
- **THEN** validation fails with a message naming the provider and the role

#### Scenario: ElevenLabs is voice-only
- **WHEN** the selected role is `chat`
- **THEN** `elevenlabs` is not offered as a provider

### Requirement: Voice systems do not require chat-only fields
For the `audio` and `transcription` roles, Model and Max Tokens SHALL be optional, and fields that only affect chat (system prompt, tool grants, temperature, context length, model capabilities, web tool policy) SHALL be hidden and not required. A voice system MAY store a default voice name, used when a speech request names none.

#### Scenario: Creating a voice system with a blank model
- **WHEN** an administrator creates an `audio` system without a model or max tokens
- **THEN** the system is saved

#### Scenario: Default voice is stored
- **WHEN** an administrator saves an `audio` system with a default voice of `af_heart`
- **THEN** speech generated through that system uses `af_heart` unless a request specifies a voice

### Requirement: Voice feature defaults match the system's role
Feature defaults SHALL include `voice:dictation` and `voice:speech`. Only a `transcription` system SHALL be assignable as the `voice:dictation` default, only an `audio` system as the `voice:speech` default, and only a `chat` system as the default for a chat feature (`targeted-resume`, `cover-letter`, `company-intel`).

#### Scenario: Assigning a voice default
- **WHEN** an administrator saves a `transcription` system with the `voice:dictation` default
- **THEN** that system becomes the dictation default, replacing any previous one

#### Scenario: Mismatched default is rejected
- **WHEN** an administrator saves an `audio` system with the `targeted-resume` default
- **THEN** validation fails and no default changes

### Requirement: Voice systems are not offered as chat models
Every picker that selects an AiSystem to hold a conversation SHALL list only `chat` systems. This includes a persona's backing system and the system choices on the New Session / I Applied flow.

#### Scenario: Persona system picker
- **WHEN** an administrator opens a persona's create or edit form while an active `audio` system exists
- **THEN** the `audio` system is not among the selectable systems

#### Scenario: New Session system list
- **WHEN** the New Session page lists selectable systems while an active `transcription` system with a feature default exists
- **THEN** the `transcription` system is not listed

### Requirement: The systems list shows each system's role
The AI systems index SHALL show the role of each system.

#### Scenario: Index shows role
- **WHEN** an administrator views the AI systems index
- **THEN** each row indicates whether the system is chat, audio or transcription
