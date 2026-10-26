# ai-system-voice-roles

## Purpose

Defines how an administrator creates and maintains AiSystems that serve speech-to-text or text-to-speech rather than chat, and keeps those voice systems out of every place that selects a chat model.

## Requirements

### Requirement: An AiSystem is created with a role
Every AiSystem SHALL have a role of `chat`, `audio` (text-to-speech) or `transcription` (speech-to-text). Systems created from the AI Systems pages SHALL be `chat`; the Voice Systems create page SHALL offer `transcription` ("Dictation") and `audio` ("Read aloud"). A request without a role SHALL create a `chat` system. The role SHALL be locked after creation under the same rule as Provider: editable only while the system is a pending duplicate.

#### Scenario: Creating a voice system
- **WHEN** an administrator creates a system on the Voice Systems page with role `transcription`
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
Every picker that selects an AiSystem to hold a conversation SHALL list only `chat` systems. This includes a persona's backing system, the system choices on the New Session / I Applied flow, and the systems eligible for Company Intel. The host SHALL read these through a chat-only model (`ChatSystem`) rather than by remembering a role filter at each query, and the Voice Systems pages through a voice-only model (`VoiceSystem`).

#### Scenario: A chat-only model cannot return a voice system
- **WHEN** an audio system exists and the host queries `ChatSystem`
- **THEN** the audio system is not returned
- **AND** `VoiceSystem` returns it, while not returning any chat system

#### Scenario: Persona system picker
- **WHEN** an administrator opens a persona's create or edit form while an active `audio` system exists
- **THEN** the `audio` system is not among the selectable systems

#### Scenario: New Session system list
- **WHEN** the New Session page lists selectable systems while an active `transcription` system with a feature default exists
- **THEN** the `transcription` system is not listed

### Requirement: Voice systems have their own pages
The AI Systems index SHALL list only `chat` systems. A separate Voice Systems page (`/admin/ai/voice-systems`, `manage-ai-tools`) SHALL list the `audio` and `transcription` systems with what each is used for, and SHALL have its own create and edit forms holding only the fields a voice system uses. Opening a voice system's edit page from the AI Systems URL SHALL redirect to its Voice Systems page; a chat system's id SHALL NOT be found on the Voice Systems pages. Both forms save through the same validation.

#### Scenario: Lists are split by role
- **WHEN** an administrator views the AI Systems index while chat, audio and transcription systems exist
- **THEN** only the chat systems are listed
- **AND** the Voice Systems page lists only the audio and transcription systems, each with its use

#### Scenario: Edit page follows the role
- **WHEN** an administrator opens `/admin/ai/systems/{id}` for an audio system
- **THEN** they are redirected to that system's Voice Systems edit page

### Requirement: The AI system form groups provider fields
On the system forms, Name SHALL span the full width. Provider, API Key and Model SHALL share one row where the width allows and stack one per line where it does not. Base URL SHALL span its own line. On the chat form, API Version, Max Tokens, Context Length and Temperature SHALL appear as two rows of two columns at every width, phones included.

#### Scenario: Phone width
- **WHEN** the chat system form is viewed at phone width
- **THEN** Provider, API Key and Model each take a full line
- **AND** API Version, Max Tokens, Context Length and Temperature form a two-by-two grid
