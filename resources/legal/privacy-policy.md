# Privacy Policy

**Effective date:** September 8, 2026  
**Last updated:** September 8, 2026

## 1. Who we are

This website (`jasonvertucio.com`, together with its subpages and the services
described below, the "Site") is operated by **The Bootstrap Paradox, LLC**, a
Pennsylvania limited liability company ("we", "us", "our"). For the purposes of
the EU and UK General Data Protection Regulation, The Bootstrap Paradox, LLC is
the **controller** of the personal data described in this policy.

Contact for all privacy matters, including requests to exercise your rights:

> **The Bootstrap Paradox, LLC**  
> Email: **info@bspdx.com**

We have not appointed a Data Protection Officer, and we are not required to do
so. We have not appointed an Article 27 representative in the EU or UK; if you
are in the EU or UK, contact us directly at the address above.

## 2. Scope of this policy

This policy explains what personal data the Site collects, why we collect it,
who receives it, how long we keep it, and what rights you have. It covers:

- the public site and personal portfolio;
- the blog and its comment system;
- the resume viewer, share-code access, and document downloads;
- the AI chat assistants available on the Site;
- user accounts and their authentication methods.

It does not cover third-party websites we link to, which have their own
policies.

## 3. Personal data we collect

### 3.1 Data you give us directly

| What | Where it comes from | Notes |
| --- | --- | --- |
| Name | Comment form; account profile | Required to post a comment. Stored with the comment permanently so the comment survives even if an account is later deleted. |
| Email address | Comment form; account registration; contact | Optional for comments. Required for an account. Comment email addresses are never displayed publicly. |
| Comment content | Comment form | Published publicly on the Site, together with your name. |
| Account credentials | Registration and account settings | Passwords are stored only as a salted hash. We also store two-factor (TOTP) secrets, two-factor recovery codes, and public-key credentials for passkeys. We never store a passkey private key — it never leaves your device. |
| Authentication preferences | Account settings | For example, whether passwordless sign-in is enabled for your account. |
| Chat messages | AI chat assistants | The full content of what you type, and the assistant's replies. See section 6. |
| Resume share code | Resume access form | The six-character code you were given, recorded with each view and download. |

### 3.2 Data collected automatically

| What | Purpose |
| --- | --- |
| IP address | Security, abuse prevention, rate limiting, and blocking. Where the Site is served through Cloudflare, we read the originating address from Cloudflare's forwarding headers. |
| Browser user-agent string | Security, abuse investigation, and troubleshooting. |
| Request metadata | Requested URL, timestamp, referring page, and response status, recorded in ordinary server logs. |
| Cookies and similar identifiers | See section 5. |
| Analytics events | Pages viewed, approximate location derived from IP, and device/browser characteristics, collected through Google Analytics. See section 5.2. |

We specifically record IP address and user-agent alongside **comments**, and
alongside each **resume view and download** (with the version accessed and the
share code used, if any). This is deliberate: it is what lets us investigate
spam and abuse, and it is what would feed a decision to block an address.

### 3.3 Data we do not collect

- We do not collect payment card or financial information; the Site takes no
  payments.
- We do not knowingly collect special-category data under GDPR Article 9, or
  "sensitive personal information" as defined by the CCPA, and we ask that you
  not submit it — particularly to the AI chat assistants.
- We do not buy personal data from data brokers or enrich your record from
  third-party sources.
- The "currently watching" feature on the home page displays the **site
  owner's own** media playback, sent to the Site by the owner's private media
  server. It collects nothing about you.

## 4. Why we use your data, and our legal bases

Under GDPR Article 6, we rely on the following bases.

| Purpose | Data used | Legal basis |
| --- | --- | --- |
| Displaying the Site and keeping your session working | Session cookie, CSRF token, IP | **Legitimate interests** (Art. 6(1)(f)) — operating a functioning website. These cookies are strictly necessary. |
| Publishing your comment and its thread position | Name, comment content, timestamps | **Legitimate interests** — running a blog with public discussion, at your own initiative. |
| Notifying the site owner that a comment was posted | Name, email, comment content | **Legitimate interests** — moderating our own site. |
| Detecting spam and abuse; rate limiting; blocking addresses | IP, user-agent, submission timing, comment content | **Legitimate interests** — protecting the Site and its users from abuse. |
| Creating and securing your account; signing you in | Email, password hash, TOTP secret, passkey credentials | **Performance of a contract** (Art. 6(1)(b)) and **legitimate interests** in account security. |
| Providing the resume viewer and download, and enforcing share codes | Share code, IP, user-agent, account ID, version accessed | **Legitimate interests** — controlling access to a document shared selectively, and knowing whether a code has been passed around. |
| Operating the AI chat assistants | Chat messages and replies, conversation identifiers | **Legitimate interests** in providing the feature, and, where you volunteer information beyond what the feature needs, your **consent** (Art. 6(1)(a)) implied by your choice to type it. |
| Measuring Site traffic | Analytics identifiers, pages viewed, approximate location | **Consent** (Art. 6(1)(a)) where consent is required in your jurisdiction; otherwise legitimate interests in understanding audience size. |
| Complying with law and responding to lawful requests | Any of the above | **Legal obligation** (Art. 6(1)(c)) and establishing or defending legal claims (Art. 9(2)(f) where relevant). |

Where we rely on legitimate interests, we have considered your interests and
rights and concluded they are not overridden — the data involved is limited,
the processing is what a visitor would reasonably expect from a personal blog
with comments, and you can object at any time (section 9).

## 5. Cookies and tracking

### 5.1 Strictly necessary cookies

These are set by the Site itself and cannot be switched off without breaking
core functionality:

| Cookie | Purpose | Lifetime |
| --- | --- | --- |
| Session cookie | Keeps you signed in and preserves form state between requests. | Browser session, or up to the configured session lifetime. |
| `XSRF-TOKEN` | Cross-site request forgery protection on forms. | Same as the session. |
| "Remember me" cookie | Only set if you choose to stay signed in. | Up to five years, or until you sign out. |

### 5.2 Analytics

The Site loads **Google Analytics** (property `UA-11841699-3`) from Google's
tag manager domain. Where it is active, it sets its own cookies and shares
your IP address and browsing activity on this Site with Google. Analytics is
disabled entirely when the Site runs in debug mode.

You can prevent analytics from loading by using a content blocker or Google's
own opt-out browser add-on. Blocking it does not affect any other part of the
Site.

### 5.3 Third parties that receive your IP address by page load

Because your browser fetches these resources directly, the following providers
necessarily receive your IP address and user-agent whenever you load a page
that uses them, whether or not you interact with anything:

- **Google Fonts** (`fonts.googleapis.com`, `fonts.gstatic.com`) — web fonts.
- **jsDelivr** (`cdn.jsdelivr.net`) — JavaScript libraries.
- **Cloudflare** — where it fronts the Site, as a network intermediary.

### 5.4 Do Not Track and Global Privacy Control

We do not currently respond to browser Do Not Track signals, because there is
no consistent industry standard for them. We do treat a **Global Privacy
Control (GPC)** signal as a valid opt-out request under the CCPA where it is
legally required — noting that we do not sell or share personal information in
any case (section 10.3).

## 6. The AI chat assistants

Some chat assistants on the Site are open to the public; others require an
account with specific permission.

**Read this before typing anything into one.** When you send a message:

1. The message, the conversation so far, and any system instructions are
   transmitted to a **third-party large-language-model provider** for
   processing. Depending on which assistant you are using, that provider may
   be **Anthropic**, **OpenAI**, or **Google**. Each is an independent
   controller or processor operating under its own terms and privacy policy.
2. The conversation, including your messages, the assistant's replies, and
   associated metadata (timestamps, conversation identifiers, token counts),
   is stored in our database.
3. Conversations may be reviewed by the site owner to improve the assistants,
   diagnose faults, or investigate abuse.

Do not submit passwords, financial details, health information, government
identifiers, another person's personal data, or anything you would not want
retained and read by a human. We do not use chat content to make automated
decisions that produce legal or similarly significant effects about you
(GDPR Article 22).

## 7. Who we share data with

We do not sell your personal data. We disclose it only as follows.

| Recipient | What they receive | Role |
| --- | --- | --- |
| Our hosting and infrastructure provider | Everything stored on the server, as an incident of hosting it | Processor |
| Cloudflare | Request metadata, IP address | Processor / network provider |
| Google (Analytics) | Analytics identifiers, IP, page views | Independent controller under its own terms |
| Google (Fonts) | IP, user-agent | Independent controller |
| jsDelivr | IP, user-agent | Independent controller |
| Anthropic, OpenAI, Google | AI chat conversation content, as applicable to the assistant used | Processor / independent controller under their own terms |
| Our outbound email provider | Recipient address and message content for notification emails | Processor |

We may also disclose personal data where we are legally required to, where it
is necessary to establish, exercise, or defend legal claims, or where it is
necessary to protect the rights, property, or safety of any person. If the
business is sold or reorganized, data may transfer as part of that
transaction; you will be notified through this policy if the controller
changes.

## 8. International transfers and retention

### 8.1 Transfers

The Site and its data are hosted in the **United States**. If you access the
Site from the European Economic Area, the United Kingdom, or Switzerland, your
personal data is transferred to the United States. Where such a transfer
requires a safeguard under Chapter V of the GDPR, we rely on the European
Commission's **Standard Contractual Clauses** (and the UK Addendum, where
applicable) as incorporated into our providers' data-processing terms, or on
the provider's certification under the **EU-U.S. Data Privacy Framework** where
it holds one. You may request further detail at info@bspdx.com.

### 8.2 Retention

| Data | How long we keep it |
| --- | --- |
| Published comments and their metadata | For as long as the associated post remains published, or until you ask us to delete them. |
| Comments marked as spam | Retained while they remain useful for abuse detection, then deleted. |
| Account records | For the life of the account. Deleted within 30 days of your closure request, except where retention is legally required. |
| Authentication credentials (password hash, TOTP secret, passkeys) | Deleted with the account, or immediately when you remove the individual method. |
| Resume view and download records | Up to 24 months from the event, then deleted or aggregated into non-identifying counts. |
| Share codes | Until revoked or expired, plus the retention period of the access records referencing them. |
| AI chat conversations | Until deleted by us as part of routine housekeeping, or on your request. |
| Server and security logs | For as long as needed for security purposes, ordinarily not more than 12 months. |
| Blocked-address records | For as long as the block remains in force, which may be indefinite. |
| Google Analytics data | Per the retention period configured in Google's own product, which we do not control on your behalf. |

## 9. Your rights under GDPR (EEA, UK, Switzerland)

If you are in the EEA, the UK, or Switzerland, you have the right to:

- **Access** — obtain confirmation of whether we process your data, and a copy
  of it (Art. 15).
- **Rectification** — have inaccurate data corrected and incomplete data
  completed (Art. 16).
- **Erasure** — have your data deleted where one of the grounds in Art. 17
  applies. Note that a comment thread's structure means a deleted comment with
  visible replies is replaced by a "[comment removed]" placeholder so the
  replies keep their position; the personal data behind it is removed.
- **Restriction** — have processing limited in the circumstances of Art. 18.
- **Portability** — receive data you provided to us in a structured,
  commonly-used, machine-readable format, and have it transmitted to another
  controller where technically feasible (Art. 20).
- **Object** — object at any time to processing based on legitimate interests,
  including profiling, on grounds relating to your particular situation
  (Art. 21). We will stop unless we can demonstrate compelling legitimate
  grounds that override your interests.
- **Withdraw consent** — where processing is based on consent, withdraw it at
  any time, without affecting the lawfulness of processing before withdrawal
  (Art. 7(3)).
- **Not be subject to automated decision-making** producing legal or similarly
  significant effects (Art. 22). We do not carry out such decision-making.

To exercise any of these, email **info@bspdx.com**. We will respond within one
month, extendable by two further months for complex requests, and we will tell
you if we need the extension. We may ask you for information sufficient to
verify that the data is yours — for a comment, that ordinarily means replying
from the email address used to post it.

You also have the right to **lodge a complaint with a supervisory authority**,
in particular in the Member State of your habitual residence, place of work, or
the place of the alleged infringement. In the UK, that is the Information
Commissioner's Office (`ico.org.uk`).

## 10. Your rights under the CCPA/CPRA (California)

### 10.1 Categories of personal information

In the twelve months preceding the effective date of this policy, we have
collected the following categories, as defined by the California Consumer
Privacy Act:

| CCPA category | Examples on this Site | Source | Business purpose |
| --- | --- | --- | --- |
| **Identifiers** | Name, email address, IP address, account identifier | Directly from you; automatically | Publishing comments; account access; security |
| **Internet or other electronic network activity** | Pages viewed, user-agent, referring page, interaction with the chat assistants | Automatically | Analytics; security; providing the feature |
| **Geolocation data** (approximate, IP-derived) | Country/region estimate | Automatically, via analytics | Understanding audience |
| **Audio, electronic, or similar information** | The text of your comments and chat messages | Directly from you | Publishing; providing the feature |
| **Inferences** | None drawn | — | — |

We do not collect the categories for financial information, biometric
information, professional or employment information about *you*, education
information, or protected classifications.

### 10.2 Sensitive personal information

We do not collect sensitive personal information for the purpose of inferring
characteristics about you, and we do not use or disclose it for purposes
requiring a "Limit the Use of My Sensitive Personal Information" link.

### 10.3 We do not sell or share your personal information

We have not sold personal information, and we have not shared it for
cross-context behavioral advertising, in the preceding twelve months. We do
not do so now. We do not knowingly sell or share the personal information of
consumers under 16.

### 10.4 Your California rights

You have the right to **know** what we have collected and how we use and
disclose it; to **delete** personal information we hold about you; to
**correct** inaccurate personal information; to **opt out** of sale or sharing
(inapplicable here, as we do neither); to **limit** the use of sensitive
personal information (inapplicable, as we do not collect it for such uses);
and to be **free from discrimination** for exercising any of these rights. We
do not offer financial incentives, so no such program can be conditioned on
your choices.

Submit a request by emailing **info@bspdx.com** with the subject line
"California Privacy Request". We will confirm receipt within 10 business days
and respond within 45 calendar days, extendable once by a further 45 days with
notice. We will verify your identity in proportion to the sensitivity of the
request; we may decline a request we cannot verify. An **authorized agent** may
submit a request on your behalf with written permission signed by you, and we
may still contact you to confirm.

### 10.5 Other U.S. state privacy laws

If you are a resident of a state with a comparable consumer privacy statute —
including Virginia, Colorado, Connecticut, Utah, Texas, Oregon, and Montana —
you have substantially the rights described in sections 9 and 10.4, including
the right to appeal a refused request. To appeal, reply to our decision and
write "Appeal" in the subject line; if we deny the appeal, your state Attorney
General's office can receive a complaint.

## 11. Security

We protect the Site with, among other measures: HTTPS transport encryption;
salted password hashing; optional TOTP two-factor authentication; WebAuthn
passkey authentication with private keys that never leave your device;
cross-site request forgery protection on every form; rate limiting on comment
submission; and automated blocking of addresses that probe for known
vulnerabilities.

No system is perfectly secure. We cannot guarantee the security of data in
transit to us or stored by us, and you send it at your own risk. If we become
aware of a breach affecting your personal data, we will notify you and the
relevant supervisory authority where the law requires it — under GDPR
Article 33, within 72 hours of becoming aware, where the breach is likely to
result in a risk to your rights and freedoms.

## 12. Children

The Site is not directed to children. We do not knowingly collect personal
data from anyone under 13, and where GDPR applies we do not knowingly collect
it from anyone under 16 without verifiable parental consent. If you believe a
child has provided us personal data, email info@bspdx.com and we will delete
it.

## 13. Links to other sites

The Site links to external projects, social profiles, and third-party
services. Following such a link takes you outside this policy's scope. We are
not responsible for the content or privacy practices of any site we link to.

## 14. Changes to this policy

We may update this policy. When we do, we will revise the "Last updated" date
at the top. Where a change materially reduces your rights or materially
expands how we use your data, we will take reasonable steps to give notice on
the Site before it takes effect. Your continued use of the Site after a change
takes effect means you accept the revised policy.

## 15. How to contact us

For any question about this policy, or to exercise any right described in it:

> **The Bootstrap Paradox, LLC**  
> Email: **info@bspdx.com**
