## Purpose

Gives the site owner speech-to-text dictation into chat, text-to-speech playback of assistant replies, and read-aloud proofreading of cover letters and targeted resumes, available only to holders of a dedicated permission.

## ADDED Requirements

### Requirement: Voice is gated by the use-voice permission
Every voice control and voice endpoint SHALL require an authenticated user holding the `use-voice` permission. The permission SHALL be created by migration and assigned to no role. A user without it SHALL see no voice controls on any page, including public persona chat pages, and SHALL be refused by every voice endpoint.

#### Scenario: Guest on a persona page
- **WHEN** an unauthenticated visitor opens a public persona chat
- **THEN** no microphone or play control is rendered

#### Scenario: Authenticated user without the permission
- **WHEN** an authenticated user without `use-voice` calls a voice endpoint
- **THEN** the request is refused with 403

#### Scenario: Holder of the permission
- **WHEN** a user holding `use-voice` opens any page that renders the chat composer
- **THEN** the microphone control is rendered

### Requirement: Voice status reports availability
A status endpoint SHALL report, separately for dictation and speech, whether voice is available. Dictation is available when an active `voice:dictation` default exists and its server responds. Speech is available under the same rule for `voice:speech`. The reachability result SHALL be cached briefly so that page loads do not each probe the server. Controls whose capability is unavailable SHALL be shown disabled with an explanation, not hidden.

#### Scenario: No default configured
- **WHEN** no system is the `voice:dictation` default
- **THEN** the status reports dictation unavailable with the reason "not configured"
- **AND** the microphone control is disabled

#### Scenario: Speech server unreachable
- **WHEN** the `voice:speech` default exists but its server does not respond within the probe timeout
- **THEN** the status reports speech unavailable with the reason "unreachable"
- **AND** play and listen controls are disabled

### Requirement: Dictation inserts a transcript without sending
The chat composer SHALL offer a microphone control that records audio while active. When recording stops, it SHALL upload the recording for transcription and insert the transcript at the cursor in the message box. Dictation SHALL never send the message: the owner reviews and sends it.

#### Scenario: Dictating a message
- **WHEN** the owner records "add a bullet about the migration project" and stops recording
- **THEN** the transcript appears in the message box
- **AND** no message is sent

#### Scenario: Existing draft is preserved
- **WHEN** the message box already contains text and the owner dictates
- **THEN** the transcript is inserted at the cursor and the existing text is kept

#### Scenario: Microphone permission denied
- **WHEN** the browser denies microphone access
- **THEN** the composer shows a message saying microphone access was denied and nothing is uploaded

### Requirement: Transcription input is validated
The transcription endpoint SHALL accept one audio file of a browser recording type (WebM, Ogg, MP4/M4A, WAV, MPEG) up to a configured maximum size, and SHALL reject other types and oversize files with 422. It SHALL be rate limited.

#### Scenario: Oversize upload
- **WHEN** an upload exceeds the configured maximum size
- **THEN** the endpoint responds 422 and no transcription is attempted

#### Scenario: Safari recording
- **WHEN** an `audio/mp4` recording is uploaded
- **THEN** it is accepted and transcribed

### Requirement: Assistant replies can be read aloud
Each finished assistant reply SHALL offer a play control that speaks the reply's text. Markdown syntax SHALL be removed before speech so that markup characters are not read, and tool activity and reasoning SHALL NOT be spoken. While audio is playing, the control SHALL stop playback. Starting another reply's playback SHALL stop the current one.

#### Scenario: Playing a reply with Markdown
- **WHEN** the owner plays a reply containing `**Strong fit** for the role`
- **THEN** the speech says "Strong fit for the role" without reading asterisks

#### Scenario: Streaming reply
- **WHEN** a reply is still streaming
- **THEN** its play control is not offered until the reply finishes

### Requirement: Documents can be proofread by ear
The cover letter and targeted resume edit pages SHALL offer a listen control that reads the document's body aloud in order, one paragraph or bullet at a time. The owner SHALL be able to stop playback at any point. Text is spoken as currently shown in the editor, including unsaved edits.

#### Scenario: Listening to a cover letter
- **WHEN** the owner presses listen on a cover letter's edit page
- **THEN** the letter body is spoken paragraph by paragraph, in order

#### Scenario: Stopping mid-document
- **WHEN** the owner stops playback during the third paragraph
- **THEN** playback stops and no further paragraphs are requested

### Requirement: Speech input is bounded
The speech endpoint SHALL reject text that is empty after Markdown removal, or longer than a configured maximum per request, with 422. Longer content is split into paragraphs by the client. The endpoint SHALL be rate limited.

#### Scenario: Over-length text
- **WHEN** a speech request exceeds the configured maximum length
- **THEN** the endpoint responds 422 and no speech is generated

### Requirement: Generated speech is cached
Speech SHALL be cached by the combination of spoken text, voice, model and system. A repeated request for the same combination SHALL be served from the cache without calling the speech server. Cache entries older than a configured age SHALL be removed by a daily scheduled sweep.

#### Scenario: Replaying a reply
- **WHEN** the owner plays the same reply twice
- **THEN** the speech server is called once

#### Scenario: Changing the default voice
- **WHEN** the speech system's default voice changes and a previously played reply is played again
- **THEN** new speech is generated in the new voice

#### Scenario: Sweep
- **WHEN** the daily sweep runs
- **THEN** cache entries older than the configured age are deleted and newer ones are kept

### Requirement: Voice failures are reported without internal detail
When the speech server is unreachable, times out or returns an error, the endpoint SHALL respond 503 with a generic "voice unavailable" message, and SHALL log the underlying error. Provider error text, URLs and addresses SHALL NOT reach the browser. Recorded audio SHALL NOT be retained after the transcription request completes.

#### Scenario: Tunnel down during dictation
- **WHEN** the owner stops recording and the speech server cannot be reached
- **THEN** the composer shows "Voice is unavailable" and the draft is unchanged
- **AND** the response body does not contain the server's address
