## Purpose

Defines which blog posts can be read at their address, so that a draft or a
scheduled post stays private until it is published.

## Requirements

### Requirement: Only published posts are publicly readable

A post SHALL be served at its address to an anonymous visitor only when it has a
publication date that has arrived. A draft or a post scheduled for the future
SHALL answer exactly as an address with no post does.

#### Scenario: A published post

- **WHEN** an anonymous visitor requests a published post's address
- **THEN** the post is shown

#### Scenario: A scheduled post

- **WHEN** an anonymous visitor requests the address of a post whose publication date is in the future
- **THEN** the response is the same not-found response an unknown address gives, and no view of the post is recorded

#### Scenario: A draft

- **WHEN** an anonymous visitor requests the address of a post with no publication date
- **THEN** the response is not found, not a server error

### Requirement: Blog authors can preview an unpublished post

A signed-in user who may manage the blog SHALL be able to read an unpublished
post at its address, and the page SHALL state that the post is not published.
A preview SHALL NOT be recorded as a view.

#### Scenario: An author previews a scheduled post

- **WHEN** a signed-in blog author requests a scheduled post's address
- **THEN** the post is shown with a notice that it is not yet published, and no view is recorded

#### Scenario: A signed-in user without blog access

- **WHEN** a signed-in user who may not manage the blog requests a draft's address
- **THEN** the response is not found

### Requirement: Unpublished posts are not commentable

A comment SHALL NOT be accepted on a post that is not published.

#### Scenario: Commenting on a scheduled post

- **WHEN** a comment is submitted to a scheduled post's comment address
- **THEN** the response is not found and no comment is stored

### Requirement: Listings contain only published posts

Topic and tag listings, and the post counts shown with them, SHALL include only
posts that are published.

#### Scenario: A scheduled post in a topic

- **WHEN** a visitor opens the listing for a topic that contains one published and one scheduled post
- **THEN** only the published post is listed and the count is one

#### Scenario: A scheduled post under a tag

- **WHEN** a visitor opens the listing for a tag that contains a scheduled post
- **THEN** the scheduled post is not listed
