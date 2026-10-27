## Purpose

Lets an admin move an Application or Company Intel AI session to a different AI system partway through. The move is limited to systems whose prompt fits the session's feature, so the conversation and its saved prompt stay coherent.

## ADDED Requirements

### Requirement: Systems eligible for a targeted-resume session

An AI system SHALL be eligible for a targeted-resume session only when it is an active chat system and at least one of the following holds: it is the `targeted-resume` feature default, it is the `cover-letter` feature default, or its assigned system prompt is the built-in Targeted Resume prompt (id 4) or Cover Letter prompt (id 5). Every place that starts a targeted-resume session SHALL offer only eligible systems and SHALL reject an ineligible one: Begin Analysis on the New Session page, "I Applied" with an AI system selected, and Begin Analysis on the Discussion page. Starting with no system chosen SHALL keep using the `targeted-resume` default.

#### Scenario: Picker offers only matching systems

- **WHEN** the admin opens the New Session page
- **THEN** the AI system choices are exactly the eligible systems
- **AND** an active chat system whose prompt is neither id 4 nor id 5 and which holds neither default is not offered

#### Scenario: Ineligible system rejected on start

- **WHEN** a request to begin analysis names an existing AI system that is not eligible
- **THEN** the request is rejected with a validation error naming the field
- **AND** no Application or session is created or attached

#### Scenario: Existing session on an ineligible system

- **WHEN** the admin opens an application whose session runs on a system that is no longer eligible
- **THEN** the chat continues to work on that system

### Requirement: Systems eligible for a Company Intel session

An AI system SHALL be eligible for a Company Intel session when it is the active `company-intel` feature default, or an active chat system assigned the built-in Company Intel prompt (id 6). This is the same rule that already governs starting intel.

#### Scenario: Intel switch choices

- **WHEN** the admin opens the switch control on an intel page
- **THEN** the choices are exactly the systems eligible for Company Intel

### Requirement: Switching a session's AI system

The Discussion page chat and the intel page chat SHALL each offer a control to switch the session to another eligible AI system. The control SHALL show the session's current system. It SHALL list every eligible system other than the current one and SHALL be hidden when there is none. A switch SHALL be permanent: every later turn of the session SHALL run on the new system, including background and retried intel turns, until the admin switches again. The control SHALL NOT be offered for an application without an AI session.

#### Scenario: Switching an Application session

- **WHEN** the admin switches an Application session from system A to an eligible system B and confirms
- **THEN** the session's next turn is sent to system B
- **AND** the page shows system B as the current system without a manual reload

#### Scenario: Switching an intel session

- **WHEN** the admin switches an intel session to an eligible system and confirms
- **THEN** the next follow-up turn, and any Retry, runs on the new system

#### Scenario: No other eligible system

- **WHEN** the current system is the only eligible system for the session's feature
- **THEN** no switch control is shown

#### Scenario: Switching to an ineligible system

- **WHEN** a switch request names a system that is not eligible for the session's feature
- **THEN** the request is rejected and the session keeps its current system

#### Scenario: Switching to the current system

- **WHEN** a switch request names the session's current system
- **THEN** the request is rejected and no transcript note is added

### Requirement: The session keeps its prompt across a switch

Switching SHALL NOT change the session's saved instructions. After a switch, the new system SHALL receive the same instructions the session had before, followed by the same transcript. Only the system that receives them changes.

#### Scenario: Prompt unchanged after switch

- **WHEN** a targeted-resume session created on the system that holds both the `targeted-resume` and `cover-letter` defaults is switched to a system assigned only prompt 4
- **THEN** the next turn's instructions still include the cover letter guidance the session started with

### Requirement: A switch is recorded in the transcript for display only

Each switch SHALL add one note to the session's transcript naming the previous and the new system. The note SHALL be shown in the chat, marked distinctly from typed messages. It SHALL NOT be sent to any AI system as part of a later turn, and it SHALL NOT be treated as the admin's latest message when a turn decides what to answer.

#### Scenario: Note shown

- **WHEN** a session is switched from "Sonnet" to "Bonsai for Jobs"
- **THEN** the chat shows a note marked as a system switch that names both systems

#### Scenario: Note not sent to the model

- **WHEN** the admin sends a message after a switch
- **THEN** the turn's history and prompt contain the earlier transcript and the new message, and do not contain the switch note

### Requirement: A switch is refused while a turn is running

A switch SHALL be refused while a turn of that session is in progress, and the response SHALL say why. For an intel session that means while its Role intel is `queued` or `running`. For an Application session it means while a chat turn is streaming. The switch control SHALL be disabled on the page while the page itself is streaming a turn.

#### Scenario: Intel research running

- **WHEN** the admin requests a switch while the intel session's Role intel is `running`
- **THEN** the request is refused with a conflict response saying research is still running
- **AND** the session keeps its current system and no note is added

#### Scenario: Application turn streaming

- **WHEN** a switch request arrives for an Application session while a chat turn for it is streaming
- **THEN** the request is refused with a conflict response saying a reply is still being written

### Requirement: Size warning before a switch

Before the admin confirms a switch, the page SHALL show an estimate of how much the next turn will send to the new system: the saved instructions plus the transcript, with display-only notes left out. The page SHALL compare the estimate with the new system's best-known context limit, chosen in this order: the system's configured context length; else the maximum context length reported for its model, labelled as a model maximum; else a statement that the context size is not set. The page SHALL say the transcript is likely to exceed the limit when the estimate reaches the configured context length, and SHALL say it is near the limit at 80% or more of it. A model maximum SHALL never be presented as confirmation that the transcript fits. The page SHALL also say that results of earlier tool calls are not carried over and that only the chat text is. The warning SHALL NOT block the switch.

#### Scenario: Configured limit exceeded

- **WHEN** the estimate is about 38,000 tokens and the chosen system's configured context length is 8,192
- **THEN** the confirmation says the conversation is likely to exceed that system's context
- **AND** the admin can still confirm the switch

#### Scenario: Only a model maximum known

- **WHEN** the chosen system has no configured context length but its model reports a maximum of 262,144
- **THEN** the confirmation shows the estimate alongside that number labelled as the model maximum
- **AND** does not state that the conversation fits

#### Scenario: Nothing known

- **WHEN** the chosen system has neither a configured context length nor a reported model maximum
- **THEN** the confirmation shows the estimate and says the system's context size is not set

### Requirement: Switching requires resume-editing permission

Viewing switch choices and switching SHALL require `edit-resume`, the same permission as the Application and Company Intel pages.

#### Scenario: Without permission

- **WHEN** a user without `edit-resume` requests switch choices or a switch for any session
- **THEN** the request is forbidden and the session is unchanged
