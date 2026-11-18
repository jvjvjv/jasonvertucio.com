# comment-reply-notifications Specification

## Purpose

Lets a commenter ask to be emailed when someone replies directly to their comment, with double opt-in for unverified anonymous addresses, send-time safety checks, rate caps and unsubscribe, so the site never mails an address that did not ask for it.

## Requirements

### Requirement: A commenter may opt in to reply emails
Every comment form, for anonymous visitors and signed-in users alike, SHALL offer an "Email me when someone replies" choice that is off by default. Leaving it off SHALL record no subscription and send no email. The choice SHALL be stored per comment.

#### Scenario: The choice is off by default
- **WHEN** a visitor opens any comment or reply form on a published post
- **THEN** the form shows the reply-email choice, unchecked

#### Scenario: Not opting in sends nothing
- **WHEN** a comment is submitted without the reply-email choice
- **THEN** the comment is recorded as not wanting reply emails, and no confirmation or reply email is ever sent for it

#### Scenario: A rejected submission keeps the choice
- **WHEN** a submission with the choice checked fails validation
- **THEN** the form it returns to still shows the choice checked

### Requirement: A commenter with a verified account address is subscribed without confirmation
When a signed-in user whose account address is marked verified on this site posts a comment with the reply-email choice, the subscription SHALL be active immediately and no confirmation email SHALL be sent. If their address was on the opt-out list, opting in SHALL remove it from the list. Signing in alone SHALL NOT count as verification: a signed-in user whose account address is not marked verified SHALL confirm by email exactly as an anonymous visitor does, and their opt-in SHALL NOT lift an opt-out until confirmed.

#### Scenario: A verified user opts in
- **WHEN** a signed-in user with a verified account address posts a comment with the reply-email choice checked
- **THEN** the comment's subscription is active at once and no confirmation email is queued

#### Scenario: A verified user who had opted out opts in again
- **WHEN** a signed-in user with a verified account address that is on the opt-out list posts a comment with the reply-email choice checked
- **THEN** the address is removed from the opt-out list and the comment's subscription is active

#### Scenario: An unverified signed-in user opts in
- **WHEN** a signed-in user whose account address is not verified posts a comment with the reply-email choice checked
- **THEN** a confirmation email is queued to their account address, the subscription is pending, and an opt-out of that address stays in place until the link is confirmed

### Requirement: An unverified commenter must confirm before receiving reply emails
When an anonymous visitor, or a signed-in user whose account address is not verified, posts a comment with the reply-email choice, the site SHALL queue a confirmation email to the address given and SHALL NOT send any reply email for that comment until the subscription is confirmed. The confirmation email SHALL carry a signed, expiring link to a page that confirms only when the visitor presses a button on it; merely loading the page SHALL NOT confirm. The page SHALL refuse a link whose signature is invalid or has expired, and SHALL refuse to confirm a comment that is no longer visible. Confirming SHALL remove the confirmed address (the one the confirmation email was sent to) from the opt-out list if it is on it.

#### Scenario: Opting in queues a confirmation email
- **WHEN** an anonymous visitor posts a comment with the reply-email choice checked
- **THEN** a confirmation email is queued to the address they gave, and the subscription is pending

#### Scenario: A reply before confirmation sends nothing
- **WHEN** someone replies to a comment whose subscription is still pending
- **THEN** no reply email is sent

#### Scenario: Loading the confirmation link does not confirm
- **WHEN** the signed confirmation link is requested with GET (for example by a mail scanner prefetching it)
- **THEN** a page with a confirm button is shown and the subscription is still pending

#### Scenario: Pressing confirm activates the subscription
- **WHEN** the visitor presses the confirm button on the page reached through a valid link
- **THEN** the subscription becomes active and the page says so

#### Scenario: A tampered or expired link is refused
- **WHEN** the confirmation page is requested with a missing or altered signature, or after the link has expired
- **THEN** the request is refused and the subscription is unchanged

#### Scenario: Confirming lifts an earlier opt-out
- **WHEN** the visitor confirms a subscription for an address that is on the opt-out list
- **THEN** the subscription becomes active and the address is removed from the opt-out list

#### Scenario: A hidden comment cannot be confirmed
- **WHEN** the confirm button is pressed for a comment that has since been marked spam or deleted
- **THEN** the subscription is not activated

### Requirement: Confirmation emails are limited per address
The site SHALL send no more than a configured number of confirmation emails to the same address (compared case-insensitively) within 24 hours, counting both new opt-ins and requests for a new link. A comment whose confirmation email is withheld SHALL still be posted, with its subscription left pending. An address on the opt-out list SHALL still be sent a confirmation email when a new comment opts in with it, because the opt-out is not permanent; until that confirmation is completed, the address stays opted out.

#### Scenario: An opted-out address can opt back in
- **WHEN** an anonymous comment opts in using an address on the opt-out list
- **THEN** the comment is posted, a confirmation email is queued, and the address stays on the opt-out list until the link is confirmed

#### Scenario: The daily cap is reached
- **WHEN** an anonymous comment opts in using an address that has already been sent the configured number of confirmation emails in the last 24 hours
- **THEN** the comment is posted and no further confirmation email is queued

#### Scenario: Address comparison ignores case
- **WHEN** two opt-ins use the same address in different letter case
- **THEN** both count against the same daily cap

### Requirement: A direct reply notifies the subscribed parent's author
When a reply is posted to a comment whose subscription is active, the site SHALL email that comment's author. Only the direct parent SHALL be notified; earlier comments higher in the thread SHALL NOT be. A signed-in author whose account address is verified SHALL be emailed at that current address; otherwise the author SHALL be emailed at the address recorded on the comment, which is the address that was confirmed or verified when they opted in.

#### Scenario: A reply to a subscribed comment
- **WHEN** someone replies directly to a comment whose subscription is active
- **THEN** that comment's author is emailed about the reply

#### Scenario: A deeper reply notifies only its own parent
- **WHEN** a reply is posted to a reply, and both that reply and the comment above it have active subscriptions
- **THEN** only the author of the reply being answered is emailed

#### Scenario: A top-level comment notifies no commenter
- **WHEN** a top-level comment is posted
- **THEN** no reply email is sent to any commenter

#### Scenario: A verified author is emailed at their current address
- **WHEN** a signed-in author's account address has changed since they commented and the new address is verified, and their comment receives a reply
- **THEN** the reply email goes to the account's current address

#### Scenario: An unverified address change does not redirect reply emails
- **WHEN** a signed-in author's account address has changed to one that is not verified, and their comment receives a reply
- **THEN** the reply email goes to the address recorded on the comment, not the new account address

### Requirement: A reply email is withheld when it should not go out
A reply email SHALL be sent only after a configured delay, and the following SHALL be checked when it is about to send rather than when the reply was posted. The email SHALL NOT be sent if any of these hold at that moment: the reply is no longer visible (spam or deleted); the parent comment is no longer visible; the post is no longer published; the parent's subscription is no longer active; the recipient address is on the opt-out list; the recipient is the reply's own author (same account, or the same address compared case-insensitively); or the recipient is the site owner.

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
- **WHEN** the author of a subscribed comment replies to it, as the same account or with the same address in any letter case
- **THEN** no reply email is sent

#### Scenario: Someone replies to the site owner
- **WHEN** someone replies to a subscribed comment written by the site owner
- **THEN** no reply email is sent, and the owner's existing new-comment notification is unaffected

#### Scenario: The subscriber has unsubscribed since
- **WHEN** the subscriber unsubscribes after a reply is posted but before its email sends
- **THEN** no reply email is sent

### Requirement: Reply emails are capped per address
The site SHALL send no more than a configured number of reply emails to the same address (compared case-insensitively) per hour. Replies beyond the cap SHALL NOT be emailed; the replies themselves SHALL still be posted.

#### Scenario: A burst of replies
- **WHEN** more replies to one subscriber's comments arrive within an hour than the configured cap
- **THEN** only the cap's worth of reply emails are sent, and every reply still appears on the post

### Requirement: The reply email reveals nothing private and cannot carry links from the reply
The reply email SHALL name the post, the replier's display name, and the reply's text, and SHALL link to the reply on the post. It SHALL NOT include the replier's email address or IP address. The reply text SHALL be presented as plain text: markup or Markdown in it SHALL NOT render as links, images or formatting.

#### Scenario: The email's contents
- **WHEN** a reply email is rendered
- **THEN** it contains the post title, the replier's name, the reply text and a link to that reply on the post, and does not contain the replier's email or IP address

#### Scenario: A link written in the reply is not rendered as a link
- **WHEN** a reply's text contains a Markdown link or an HTML anchor
- **THEN** the email shows it as literal text, not as a clickable link

### Requirement: Every comment email can be unsubscribed from
Every confirmation email and reply email SHALL include a signed unsubscribe link that does not expire. Its page SHALL offer two actions, each taken only by pressing a button: stop reply emails for that one comment, or stop all comment emails to that address. Reply emails SHALL also carry `List-Unsubscribe` and `List-Unsubscribe-Post: List-Unsubscribe=One-Click` headers whose one-click POST stops reply emails for that comment without a page visit. A link or POST with an invalid signature SHALL be refused.

#### Scenario: Unsubscribing from one comment
- **WHEN** the recipient presses "stop emails for this comment" on the unsubscribe page
- **THEN** that comment's subscription ends and other subscriptions for the same address are unaffected

#### Scenario: Opting the address out entirely
- **WHEN** the recipient presses "stop all comment emails to this address"
- **THEN** the address is added to the opt-out list and receives no further reply emails, whatever comment they concern, until it opts in again

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

### Requirement: Reply notifications can be switched off site-wide
Reply notifications SHALL be controlled by a configuration toggle. When it is off, the form SHALL NOT offer the reply-email choice and no confirmation or reply email SHALL be sent; existing subscriptions SHALL be kept so they resume when it is switched back on.

#### Scenario: The feature is disabled
- **WHEN** reply notifications are disabled in configuration and a reply is posted to a comment with an active subscription
- **THEN** no reply email is sent, the comment forms show no reply-email choice, and the subscription remains recorded

### Requirement: An expired or invalid link explains itself
A comment-email link whose signature is invalid or expired SHALL still be refused with a 403 status, but SHALL show a page saying what happened rather than a bare error. A confirmation link that was genuinely issued by the site but has expired SHALL show "This link has expired" with a button that emails a fresh confirmation link for the same comment. The fresh link SHALL count against the same per-address daily cap, and SHALL NOT be sent for a comment that is no longer visible, no longer wants reply emails, or is already confirmed. A link with a missing or altered signature SHALL show that the link is not valid, with no button.

#### Scenario: An expired confirmation link
- **WHEN** a genuine confirmation link is opened after it has expired, for a comment still pending confirmation
- **THEN** the response is 403, the page says the link has expired, and it offers a button to send a new link

#### Scenario: Asking for a new link
- **WHEN** the visitor presses the button to send a new link
- **THEN** a new confirmation email is queued to the comment's address and the page says a new link was sent

#### Scenario: The daily cap applies to new links
- **WHEN** the visitor asks for a new link for an address that has already had the configured number of confirmation emails today
- **THEN** no email is queued and the page says to try again later

#### Scenario: An expired link for a comment that is already confirmed
- **WHEN** an expired confirmation link is opened for a comment whose subscription is already active
- **THEN** the page says reply emails are already on and offers no button

#### Scenario: A tampered link
- **WHEN** a comment-email link is opened with a missing or altered signature
- **THEN** the response is 403 and the page says the link is not valid, with no button to send a new one
