## Purpose

Lets an authenticated user who holds blog-management permission create and update blog posts through the MCP endpoint, as drafts or published, without changing what any other caller sees.

## ADDED Requirements

### Requirement: The post-writing tool is offered only to holders of blog-management permission
The endpoint SHALL advertise a post-writing tool only to a caller whose presented bearer token resolves to a user holding the `manage-blog` permission. For every other caller — anonymous, or a token holder without that permission — the tool SHALL be absent from the tool listing and SHALL be refused if invoked by name.

#### Scenario: A permitted token holder sees the tool
- **WHEN** a caller with a valid token for a user holding `manage-blog` lists tools
- **THEN** the listing includes the post-writing tool alongside the read-only tools

#### Scenario: An anonymous caller does not see the tool
- **WHEN** a caller with no credentials lists tools
- **THEN** the listing contains only the read-only tools and is identical to the listing before this capability existed

#### Scenario: A token holder without the permission does not see the tool
- **WHEN** a caller with a valid token for a user lacking `manage-blog` lists tools
- **THEN** the post-writing tool is absent

#### Scenario: Invocation by name is refused without the permission
- **WHEN** a caller who lacks `manage-blog` invokes the post-writing tool by name
- **THEN** the call is refused and no post is created or modified

### Requirement: The tool creates a draft post by default
When invoked without an existing post identifier, the tool SHALL create a blog post owned by the calling user. Unless publication is explicitly requested, the post SHALL be created unpublished.

#### Scenario: A draft is created
- **WHEN** a permitted caller supplies a title and body and no publication option
- **THEN** a post exists with that content, no publication time, and it does not appear on the public blog

#### Scenario: A slug is generated when none is given
- **WHEN** a permitted caller supplies a title and no slug
- **THEN** the post receives a slug derived from the title under the same rules the blog editor applies

### Requirement: The tool updates an existing post
When invoked with the identifier of an existing post the caller is allowed to update, the tool SHALL change that post's supplied fields and leave unsupplied fields as they were.

#### Scenario: A post is updated
- **WHEN** a permitted caller supplies an existing post's identifier and a new title
- **THEN** the title changes and the body, summary, topic and tags are unchanged

#### Scenario: An unknown or unupdatable post is reported as not found
- **WHEN** a caller supplies an identifier that matches no post, or a post they may not update
- **THEN** the tool reports the post as not found and changes nothing, without distinguishing the two cases

### Requirement: Publishing and scheduling are explicit and validated
The tool SHALL publish a post immediately or schedule it for a future time only when the caller asks for it. Publishing or scheduling SHALL require a title, and scheduling SHALL require a timezone-qualified time in the future.

#### Scenario: A post is published immediately
- **WHEN** a permitted caller requests immediate publication of a post that has a title
- **THEN** the post becomes publicly visible on the blog

#### Scenario: A post is scheduled
- **WHEN** a permitted caller requests scheduling with a future time that carries a timezone
- **THEN** the post has that publication time and is not yet publicly visible

#### Scenario: A past or timezone-less schedule time is rejected
- **WHEN** a caller requests scheduling with a time in the past, or a time with no timezone
- **THEN** the tool reports a validation error and the post is not changed

#### Scenario: Publishing without a title is rejected
- **WHEN** a caller requests publication of a post that has no title
- **THEN** the tool reports a validation error and the post is not published

### Requirement: Topic and tags resolve only to existing records
The tool SHALL attach a topic and tags by their slugs only when matching records already exist. It SHALL NOT create topics or tags, and SHALL report any supplied slug it could not match.

#### Scenario: Existing topic and tags are attached
- **WHEN** a caller supplies the slugs of an existing topic and existing tags
- **THEN** the post is associated with them

#### Scenario: Unknown slugs are reported, not created
- **WHEN** a caller supplies a tag slug that matches no tag
- **THEN** no tag is created, the post is saved without it, and the result lists the slug as ignored

### Requirement: A post written through the tool behaves like one saved in the editor
A post created or changed through the tool SHALL trigger the same publication lifecycle events as the same change made in the blog editor, so the public blog feed and any cached blog listings reflect it without waiting for a cache to expire.

#### Scenario: Publishing refreshes caches
- **WHEN** a post is published through the tool
- **THEN** the blog feed and the public recent-posts tool include it on their next request

### Requirement: Tool results are never cached and calls do not record post content
The tool's results SHALL NOT be served from the endpoint's result cache. The call record for an invocation SHALL identify the caller and the tool but SHALL NOT store the post's title, body or other submitted content.

#### Scenario: A repeated write is executed each time
- **WHEN** a permitted caller invokes the tool twice with identical arguments
- **THEN** both calls are executed rather than the second being answered from cache

#### Scenario: The call record omits submitted content
- **WHEN** a permitted caller saves a post through the tool
- **THEN** the corresponding call record names the tool and the resolved user and contains no part of the submitted body
