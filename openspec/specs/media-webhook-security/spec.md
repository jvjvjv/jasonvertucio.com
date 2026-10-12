## Purpose

Protects the playback-event endpoint and the "now playing" display it feeds, so
that only the site owner's media server can change what the homepage shows and
nothing it sends can be interpreted as markup.

## Requirements

### Requirement: Playback events are accepted only with the shared secret

The playback-event endpoint SHALL process a request only when it carries the
configured shared secret. A request with a missing or wrong secret SHALL be
refused with 401 and SHALL NOT create, change or delete any media record. When no
secret is configured, every request SHALL be refused.

#### Scenario: The media server sends an event with the secret

- **WHEN** a playback-start event arrives carrying the configured secret
- **THEN** the event is processed and the response is 200

#### Scenario: A request without the secret

- **WHEN** a well-formed playback event arrives with no secret
- **THEN** the response is 401 and no media record is created or changed

#### Scenario: A request with a wrong secret

- **WHEN** a playback event arrives with a secret that does not match
- **THEN** the response is 401 and no media record is created or changed

#### Scenario: No secret is configured

- **WHEN** the application has no secret configured and an event arrives with any or no secret
- **THEN** the response is 401

### Requirement: Playback events are rate limited

The playback-event endpoint SHALL limit how many requests one client address may
make per minute and SHALL answer 429 beyond that limit. The limit SHALL be high
enough that a media server reporting playback progress is never refused.

#### Scenario: A flood from one address

- **WHEN** one address sends more requests in a minute than the limit allows
- **THEN** the excess requests receive 429

### Requirement: Media text is displayed as text

Every value that originated in a playback event — title, series, artist, album —
SHALL be displayed with markup characters escaped, on the homepage and in the
media API responses' consumers.

#### Scenario: An album name containing markup

- **WHEN** the currently playing item's album name is `<img src=x onerror=alert(1)>`
- **THEN** the homepage shows that string as visible text, no image is requested and no script runs

#### Scenario: An ordinary album name keeps its emphasis

- **WHEN** the currently playing item is a song from the album "Blue Train"
- **THEN** the homepage shows "From the Album" followed by the album name in emphasis
