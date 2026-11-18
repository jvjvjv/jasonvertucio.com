## ADDED Requirements

### Requirement: Addresses are compared by mailbox
Wherever the site decides whether two email addresses are the same for comment emails (the per-address caps, the opt-out list, the self-reply check and the site-owner check), it SHALL compare mailboxes rather than exact strings. Two addresses SHALL be the same mailbox when they match after: ignoring letter case and surrounding whitespace; converting the domain to the ASCII form mail is delivered to (IDNA, UTS #46), so that fullwidth letters or an ideographic full stop in a domain name the same domain, and ignoring a trailing dot on the domain; dropping a `+` and everything after it from the part before the `@`; and, for the `gmail.com` and `googlemail.com` domains, also dropping dots from the part before the `@` and treating both domains as one. Comparison SHALL NOT change where an email is sent: it SHALL go to the address exactly as entered.

#### Scenario: Sub-addressed spellings are one mailbox
- **WHEN** `Name+tag@Example.com` and `name@example.com` are compared
- **THEN** they are the same mailbox

#### Scenario: Gmail dots and domains are one mailbox
- **WHEN** `first.last@googlemail.com` and `firstlast@gmail.com` are compared
- **THEN** they are the same mailbox

#### Scenario: Look-alike domain spellings are one mailbox
- **WHEN** `victim@ｇmail.com` (fullwidth letter) and `victim@gmail.com` are compared
- **THEN** they are the same mailbox

#### Scenario: A trailing dot on the domain is the same mailbox
- **WHEN** `victim@gmail.com．` (trailing fullwidth full stop) and `victim@gmail.com` are compared
- **THEN** they are the same mailbox

#### Scenario: Dots matter outside Gmail
- **WHEN** `first.last@example.com` and `firstlast@example.com` are compared
- **THEN** they are different mailboxes

#### Scenario: An opt-out covers every spelling
- **WHEN** `name@example.com` is on the opt-out list and a reply is due to a subscriber at `name+blog@example.com`
- **THEN** no reply email is sent

#### Scenario: Mail goes to the address as entered
- **WHEN** a confirmation is sent for a comment whose address is `name+blog@example.com`
- **THEN** it is addressed to `name+blog@example.com`

## MODIFIED Requirements

### Requirement: Confirmation emails are limited per address
The site SHALL send no more than a configured number of confirmation emails to the same mailbox (see "Addresses are compared by mailbox") within 24 hours, and no more than a configured number of confirmation emails caused by the same client IP address within 24 hours, counting both new opt-ins and requests for a new link. A confirmation email SHALL be sent only when both caps allow it. The per-IP cap SHALL be checked first, and a request it refuses SHALL NOT count against the mailbox's cap, so a client that has reached its own cap cannot use up the share of a mailbox it does not own. The client IP SHALL be taken from the Cloudflare forwarding header only when that header holds a valid IP address, and otherwise from the connecting address. Each count SHALL be taken so that concurrent requests cannot together exceed a cap; a request that cannot be counted SHALL send nothing. No confirmation email SHALL be sent to an address the comment form would refuse (for example a signed-in user's account address with a quoted local part); that comment's subscription stays pending. A comment whose confirmation email is withheld SHALL still be posted, with its subscription left pending. An address on the opt-out list SHALL still be sent a confirmation email when a new comment opts in with it, because the opt-out is not permanent; until that confirmation is completed, the address stays opted out.

#### Scenario: An opted-out address can opt back in
- **WHEN** an anonymous comment opts in using an address on the opt-out list
- **THEN** the comment is posted, a confirmation email is queued, and the address stays on the opt-out list until the link is confirmed

#### Scenario: The daily cap is reached
- **WHEN** an anonymous comment opts in using an address that has already been sent the configured number of confirmation emails in the last 24 hours
- **THEN** the comment is posted and no further confirmation email is queued

#### Scenario: Address comparison ignores case
- **WHEN** two opt-ins use the same address in different letter case
- **THEN** both count against the same daily cap

#### Scenario: Spellings of one mailbox share the cap
- **WHEN** opt-ins use `name@example.com`, `name+one@example.com` and `name+two@example.com`, or `first.last@gmail.com` and `firstlast@googlemail.com`
- **THEN** each group counts against one daily cap, and confirmations beyond it are withheld

#### Scenario: One client is capped across many mailboxes
- **WHEN** comments from one IP address opt in with more different mailboxes in 24 hours than the per-IP cap
- **THEN** confirmation emails beyond the per-IP cap are withheld, and the comments are still posted

#### Scenario: A client over its cap does not use up a mailbox's share
- **WHEN** a client that has reached the per-IP cap opts in with someone else's mailbox, and that mailbox's owner then opts in from their own IP
- **THEN** the first request sends nothing and the owner's confirmation email is sent

#### Scenario: A signed-in user's account address cannot hide its mailbox
- **WHEN** a signed-in user whose account address is not verified, and is `"vic.tim"@gmail.com`, posts a comment with the reply-email choice
- **THEN** the comment is posted with its subscription pending and no confirmation email is queued

#### Scenario: A forwarding header that is not an IP is ignored
- **WHEN** a request carries an empty Cloudflare forwarding header, or one that is not an IP address
- **THEN** the per-IP cap counts against the connecting address

#### Scenario: Asking for a new link counts against the requester's IP
- **WHEN** "Send me a new link" is pressed from an IP address that has reached the per-IP cap
- **THEN** no email is queued and the page says to try again later

#### Scenario: Asking for a new link counts against the mailbox under any spelling
- **WHEN** "Send me a new link" is pressed for `v.ictim@googlemail.com` after `victim+1@gmail.com` has had the mailbox's daily share
- **THEN** no email is queued and the page says to try again later

### Requirement: A reply email is withheld when it should not go out
A reply email SHALL be sent only after a configured delay, and the following SHALL be checked when it is about to send rather than when the reply was posted. The email SHALL NOT be sent if any of these hold at that moment: the reply is no longer visible (spam or deleted); the parent comment is no longer visible; the post is no longer published; the parent's subscription is no longer active; the recipient's mailbox is on the opt-out list; the recipient is the reply's own author (same account, or the same mailbox); or the recipient's mailbox is the site owner's.

#### Scenario: The reply is marked spam during the delay
- **WHEN** a reply to a subscribed comment is marked spam before its email sends
- **THEN** no reply email is sent

#### Scenario: The parent is deleted during the delay
- **WHEN** the subscribed comment is deleted before the reply email sends
- **THEN** no reply email is sent

#### Scenario: The post is unpublished
- **WHEN** the post is unpublished before the reply email sends
- **THEN** no reply email is sent

#### Scenario: A commenter replies to themselves
- **WHEN** the author of a subscribed comment replies to it, as the same account or with another spelling of the same mailbox
- **THEN** no reply email is sent

#### Scenario: Someone replies to the site owner
- **WHEN** someone replies to a subscribed comment written by the site owner
- **THEN** no reply email is sent, and the owner's existing new-comment notification is unaffected

#### Scenario: The subscriber has unsubscribed since
- **WHEN** the subscriber unsubscribes after a reply is posted but before its email sends
- **THEN** no reply email is sent

### Requirement: Reply emails are capped per address
The site SHALL send no more than a configured number of reply emails to the same mailbox per hour. Replies beyond the cap SHALL NOT be emailed; the replies themselves SHALL still be posted.

#### Scenario: A burst of replies
- **WHEN** more replies to one subscriber's comments arrive within an hour than the configured cap
- **THEN** only the cap's worth of reply emails are sent, and every reply still appears on the post

#### Scenario: Spellings of one mailbox share the hourly cap
- **WHEN** subscribed comments by `name@example.com` and `name+blog@example.com` receive replies within an hour
- **THEN** their reply emails count against one hourly cap

### Requirement: Every comment email can be unsubscribed from
Every confirmation email and reply email SHALL include a signed unsubscribe link that does not expire. Its page SHALL offer two actions, each taken only by pressing a button: stop reply emails for that one comment, or stop all comment emails to that address. Reply emails SHALL also carry `List-Unsubscribe` and `List-Unsubscribe-Post: List-Unsubscribe=One-Click` headers whose one-click POST stops reply emails for that comment without a page visit. A link or POST with an invalid signature SHALL be refused.

#### Scenario: Unsubscribing from one comment
- **WHEN** the recipient presses "stop emails for this comment" on the unsubscribe page
- **THEN** that comment's subscription ends and other subscriptions for the same address are unaffected

#### Scenario: Opting the address out entirely
- **WHEN** the recipient presses "stop all comment emails to this address"
- **THEN** the address's mailbox is added to the opt-out list, and no spelling of that mailbox receives further reply emails, whatever comment they concern, until it opts in again

#### Scenario: Not asking for the confirmation email
- **WHEN** someone receives a confirmation email they did not ask for and follows its unsubscribe link
- **THEN** they can opt their address out entirely without confirming anything

#### Scenario: One-click unsubscribe from the mail client
- **WHEN** a mail client sends the one-click POST from a reply email's `List-Unsubscribe` header
- **THEN** that comment's subscription ends, without a CSRF token or page visit being required

#### Scenario: Loading the unsubscribe link changes nothing
- **WHEN** the unsubscribe link is requested with GET
- **THEN** a page with the two buttons is shown and no subscription changes

#### Scenario: A forged unsubscribe is refused
- **WHEN** an unsubscribe GET or POST arrives with a missing or altered signature
- **THEN** it is refused and nothing changes
