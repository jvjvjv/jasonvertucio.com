## MODIFIED Requirements

### Requirement: The endpoint advertises exactly one curated read-only roster

The endpoint SHALL advertise exactly these tools to a caller who is anonymous or who lacks `manage-blog`: resume data, recent blog posts, and site profile information. Every tool in that roster SHALL be read-only — none may create, modify, approve, or delete any stored data. The only tool reachable here that writes SHALL be the blog post-writing tool, and it SHALL be advertised and callable only for a caller whose token resolves to a user holding `manage-blog`. Tools that exist elsewhere in the system for editing resume data, reviewing resume edit candidates, or working on targeted resumes and cover letters SHALL NOT be reachable through this endpoint for any caller.

#### Scenario: Roster contains only the three read tools

- **WHEN** any client with no credentials, or with a token for a user lacking `manage-blog`, lists the endpoint's tools
- **THEN** the response contains the resume-data, recent-blog-posts and site-info tools and no others

#### Scenario: A write tool is not reachable by name

- **WHEN** a client invokes a resume-editing or candidate-approval tool name against this endpoint
- **AND** the caller holds a token whose user has every permission those tools require
- **THEN** the endpoint reports the tool as unknown and no data is changed

#### Scenario: The blog post-writing tool is the only write tool for a permitted caller

- **WHEN** a client with a token for a user holding `manage-blog` lists the endpoint's tools
- **THEN** the response contains the three read tools and the blog post-writing tool and no other tool that writes
