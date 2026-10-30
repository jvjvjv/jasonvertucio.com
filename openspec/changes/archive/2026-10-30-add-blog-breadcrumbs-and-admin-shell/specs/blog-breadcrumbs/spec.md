## Purpose

Shows a reader where they are within the blog and gives them a one-step way back up to any level above the current page.

## ADDED Requirements

### Requirement: Every blog page shows its trail

Every HTML page under `/blog` SHALL show a breadcrumb trail above its content. Each trail SHALL begin with Home, which links to the site root, followed by Blog, which links to `/blog`. The trail SHALL then follow the page's place in the blog. The RSS feed SHALL NOT contain a trail.

#### Scenario: Blog index

- **WHEN** a visitor opens `/blog`
- **THEN** the trail reads Home, Blog

#### Scenario: Topics index

- **WHEN** a visitor opens `/blog/topics`
- **THEN** the trail reads Home, Blog, Topics

#### Scenario: Tags index

- **WHEN** a visitor opens `/blog/tags`
- **THEN** the trail reads Home, Blog, Tags

#### Scenario: A topic's listing

- **WHEN** a visitor opens `/blog/topics/{slug}` for an existing topic
- **THEN** the trail reads Home, Blog, Topics, then the topic's name

#### Scenario: A tag's listing

- **WHEN** a visitor opens `/blog/tags/{slug}` for an existing tag
- **THEN** the trail reads Home, Blog, Tags, then the tag's name

#### Scenario: A post

- **WHEN** a visitor opens a published post at `/blog/{slug}`
- **THEN** the trail reads Home, Blog, then the post's title

#### Scenario: A topic reached through the short address

- **WHEN** a visitor opens `/blog/{slug}` where the slug belongs to a topic and not to a post
- **THEN** the trail matches the one at `/blog/topics/{slug}`, and its Topics entry links to `/blog/topics`

#### Scenario: A tag reached through the short address

- **WHEN** a visitor opens `/blog/{slug}` where the slug belongs to a tag and not to a post or topic
- **THEN** the trail matches the one at `/blog/tags/{slug}`, and its Tags entry links to `/blog/tags`

#### Scenario: The feed

- **WHEN** a client requests `/blog/feed`
- **THEN** the RSS response contains no breadcrumb markup

### Requirement: The trail appears whatever the page's state

The trail SHALL be shown when a listing has nothing to list, and when a blog author previews an unpublished post. An address that answers 404 SHALL NOT show a blog trail.

#### Scenario: No posts on the index

- **WHEN** a visitor opens `/blog` and there are no published posts
- **THEN** the empty message and the Home, Blog trail are both shown

#### Scenario: A topic with no published posts

- **WHEN** a visitor opens the listing of a topic that has no published posts
- **THEN** the empty message and that topic's full trail are both shown

#### Scenario: An author's preview

- **WHEN** a blog author opens an unpublished post
- **THEN** the preview notice and the Home, Blog, post title trail are both shown

#### Scenario: An unknown address

- **WHEN** a visitor opens `/blog/{slug}` where the slug matches no post, topic or tag
- **THEN** the response is 404 and contains no blog trail

### Requirement: The trail is navigable and accessible

The trail SHALL be a navigation landmark labelled "Breadcrumb" that contains an ordered list. Each entry above the current page SHALL be a link, and the last entry SHALL be plain text marked as the current page. Visual separators SHALL be hidden from assistive technology. The links SHALL be operable by keyboard and show a visible focus indicator.

#### Scenario: Links and the current page

- **WHEN** a visitor opens a topic's listing
- **THEN** Home, Blog and Topics are links, and the topic's name is not a link and is marked as the current page

#### Scenario: A single ancestor

- **WHEN** a visitor opens `/blog`
- **THEN** Home is a link, and Blog is not a link and is marked as the current page

#### Scenario: Assistive technology

- **WHEN** a screen reader reads the trail on any blog page
- **THEN** it announces a navigation landmark named "Breadcrumb" and does not announce the separators

### Requirement: Trail text is displayed safely and fits small screens

Post titles and topic and tag names SHALL be escaped in the trail. A long trail SHALL wrap within its container, so it never causes horizontal page scrolling.

#### Scenario: Markup in a title

- **WHEN** a post's title contains HTML such as `<script>`
- **THEN** the trail shows that text literally, and no element is created from it

#### Scenario: Long title at phone width

- **WHEN** a post with a long title is opened on a screen 360 pixels wide
- **THEN** the trail wraps, and the page does not scroll horizontally
