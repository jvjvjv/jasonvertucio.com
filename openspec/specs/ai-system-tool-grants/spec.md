# ai-system-tool-grants

## Purpose

Defines how an administrator chooses which tools an AI system may use, with internal tools and tools from external MCP servers granted through the same allow/disallow controls.

## Requirements

### Requirement: Remote MCP tools are granted like internal tools

When tool use is enabled, the AI system create and edit pages SHALL list every grantable tool with a checkbox: each internal tool, and each tool from every enabled MCP server. Checking a tool SHALL add its name to the system's allowed tools and unchecking SHALL remove it, the same for both kinds. A remote tool SHALL be identified by its exposed name (`{slug}__{tool}`). Saving the system SHALL persist the selection. Only checked tools SHALL be offered to the system's personas.

#### Scenario: Granting a remote tool

- **WHEN** an administrator checks `mdn__search` on an AI system and saves
- **THEN** the system's allowed tools include `mdn__search`, and a persona on that system is offered that tool

#### Scenario: Revoking a remote tool

- **WHEN** an administrator unchecks a previously granted remote tool and saves
- **THEN** the system's allowed tools no longer include it, and its personas are no longer offered it

#### Scenario: Newly synced tools are not granted automatically

- **WHEN** a server gains a new tool on sync after a system had granted some of that server's other tools
- **THEN** the new tool appears unchecked for that system and isn't offered until an administrator checks it

### Requirement: The tool picker groups tools by source

The tool picker SHALL separate internal tools from remote tools, and SHALL group remote tools under their server's name.

#### Scenario: Two servers and internal tools

- **WHEN** internal tools exist and two enabled servers each have synced tools
- **THEN** the picker shows an internal group, then one group per server labelled with that server's name, each containing only its own tools

### Requirement: Unavailable remote tools are shown but not grantable

A synced remote tool that can't be offered to a model SHALL appear in its server's group, disabled, with the reason it's unavailable. Tools of a disabled server SHALL NOT appear as grantable.

#### Scenario: Unrepresentable tool

- **WHEN** a server's catalog contains a tool marked unavailable
- **THEN** the picker shows it disabled, with its reason, and it can't be checked

### Requirement: Stale grants are visible and removable

A name in the system's allowed tools that matches no currently grantable tool SHALL still be listed in the picker, checked and marked as no longer available, so the administrator can uncheck it. Leaving it checked SHALL keep it stored and SHALL NOT cause an error.

#### Scenario: Grant from a deleted server

- **WHEN** an AI system's allowed tools include `old__lookup` and the `old` server has been deleted
- **THEN** the picker lists `old__lookup`, checked and marked as no longer available, and saving after unchecking it removes it from the allowed tools

### Requirement: Tool listing stays compatible for persona pages

The admin tool-listing endpoint SHALL keep returning each tool's `name` and `description`. It MAY add fields describing each tool's source and availability, without changing which tools are returned for a given query.

#### Scenario: Existing consumer

- **WHEN** the persona admin page requests the tools available to a given AI system
- **THEN** it receives the same tool names as before this change, each with `name` and `description`
