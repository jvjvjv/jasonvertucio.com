# AI

This site is also an MCP server. If you are an agent, you can query it directly instead of parsing these pages — skip to *Querying this site* below.

## What I have built with AI

I build AI systems rather than call AI APIs. Most of what follows runs this site, and all of it is mine.

### Code Talker

A Laravel package I extracted from this application and published to Packagist. It is the conversational layer underneath everything else here: conversations, messages, tool calls and their results, attachments, streaming responses, and per-conversation usage accounting.

The most exciting part about this is the tool dispatch. The host application (in this case [jasonvertucio.com](https://jasonvertucio.com)) registers directories of its own tools, and the package discovers them, resolves their dependencies, and hands them to whichever model is running. 

That indirection is what lets the same tool class serve two completely different consumers without knowing it. So, here, one definition of "read the resume" answers both the chat bots and the public endpoint documented below.

It bridges multiple providers behind one interface: hosted models and models running locally on my own hardware, chosen per persona rather than per application.

### Personas with different tools and different audiences

A persona here is two records. One declares which tools it may use at all; the other declares what permission, if any, a person needs to talk to it. The gates are independent, so a tool is reachable only when the persona is allowed it *and* when the human in the conversation is entitled to it. Some personas on this site are public. Others are invisible unless you hold the right permission. All of the public ones are just for funsies.

### Resume editing, but with human approval

An authorized persona can propose changes to my resume from chat. Those edits never touch the live resume. They accumulate on a draft revision—batched by a rolling time window, so a conversation's worth of edits becomes one coherent revision rather than a dozen—and stay pending until a human reviews and publishes them at an explicit new version. Publishing regenerates the documents.

The persona can list what is pending and publish a revision itself, but every action it takes is written into the transcript as the assistant's own, never attributed to me. An agent that edits your record should leave its fingerprints on the edit.

### Targeted resumes and cover letters

Given a job description, a persona loads my base resume, assesses fit, and writes a tailored resume and a cover letter against it. Those render through the same pipeline as everything else: one Word template, one Markdown parse, two emitters — one producing OOXML for the .docx, one producing HTML that WeasyPrint renders to PDF. Editing the template in Word restyles every document type in both formats, because there is only one place the styling lives.

### Memory

Personas recall facts across conversations, scoped to the person they are talking to rather than pooled globally.

## Querying this site

This site runs a Model Context Protocol server. It is read-only, and it needs no credentials.

- **Endpoint:** `https://jasonvertucio.com/mcp`
- **Transport:** streamable HTTP — JSON-RPC over `POST`. A `GET` answers `405` by design; that is the transport saying it does not push server-initiated messages, not an error.

### What you get without credentials

Effectively everything a recruiter or a curious agent would want.

`get-resume-data` returns my full resume—experience, skills, education and selected projects.
`get-recent-blog-posts` returns recent writing with titles, summaries, topics and URLs, and takes a `search` argument if you are looking for whether I have written about something in particular.
`get-site-info` returns the site's own profile: selected projects and interests.

The one thing withheld from an anonymous caller is **direct contact information**, my email address and phone number. 

This isn't secrecy; those details are on my resume and I hand it out freely. It's just that a structured endpoint returning clean JSON can be harvested at machine speed in a way a web page cannot. And I don't know about you, but I'd hate for my phone number to be scraped because I'm already getting enough spam calls as it is!

### What a token adds

A bearer token returns everything its owner is permitted to see, contact details included. Tokens are issued on request rather than self-service—get in touch through the site and ask, and say roughly what you are building.

A reduced payload should never be mistaken for a complete one, so a mistyped or revoked  token is **rejected outright with a 401**.
