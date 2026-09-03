## ADDED Requirements

### Requirement: User-facing text calls chat bots "Agents"

Rendered UI text on the public chat surface and the admin AI tools surface SHALL refer to chat bots as "Agent(s)", not "Chat Bot(s)", "ChatBot(s)", or "Chatbot(s)". This applies to page titles, headings, navigation labels, empty states, confirmation dialogs, and descriptive text — anything a user or admin reads, as distinct from route paths, URLs, class/file/prop names, and JSON/API field names, which are unaffected.

#### Scenario: Admin bot management pages use "Agent" wording

- **WHEN** an admin views the AI agent list, create, or edit pages under `/admin/ai/chat-bots`
- **THEN** every page title, heading, empty state, and confirmation dialog on those pages reads "Agent(s)" rather than "Chat Bot(s)"

#### Scenario: Public chat surface uses "Agent" wording

- **WHEN** a visitor views the chat bot index or an individual chat page
- **THEN** every heading, empty state, and descriptive caption on those pages reads "Agent(s)" rather than "Chatbot(s)"

#### Scenario: AI system pages reference agents, not chat bots

- **WHEN** an admin views an AI system's edit or index page showing how many chat bots use that system
- **THEN** the count and any related descriptive text reads "Agent(s)" rather than "Chat Bot(s)"

### Requirement: New chat-bot-related UI text follows the same convention

Any UI text added after this change that refers to the chat bot concept SHALL use "Agent" rather than reintroducing "Chat Bot"/"Chatbot" wording, so the terminology doesn't drift back.

#### Scenario: A new UI string about chat bots is added later

- **WHEN** a future change adds new user-facing text describing the chat bot concept (e.g. a new empty state, tooltip, or page title)
- **THEN** that text uses "Agent" terminology, consistent with the existing UI
