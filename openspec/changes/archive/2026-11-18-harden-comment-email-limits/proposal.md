## Why

A security review of `comment-reply-notifications` found that the per-address limits and the opt-out list match addresses only ignoring case, while mailboxes accept many spellings of one address: `name+anything@domain`, and for Gmail any dots in the name and the `googlemail.com` domain. Anyone posting anonymous comments could rotate spellings to send one inbox far more than the 3 confirmation emails a day the feature promises, from the site's domain, and slip past that inbox's opt-out. The comment rate limit (5 posts a minute per IP) was the only real bound. This must be fixed before the feature reaches production.

## What Changes

- Addresses are compared **by mailbox** wherever the feature compares them: the daily confirmation cap, the hourly reply cap, the opt-out list, and the self-reply and site-owner checks. A mailbox key lower-cases the address, drops a `+tag` from the local part, and for `gmail.com` / `googlemail.com` also drops dots and treats both domains as `gmail.com`. Emails are still sent to the address exactly as entered.
- A new **per-IP daily cap on confirmation emails** (default 5), counted against the commenter's IP for a new comment and the requester's IP for "Send me a new link". A confirmation goes out only if both the per-address and per-IP caps allow it.
- The mailbox key also converts the domain to its ASCII (IDNA) form, so `victim@ｇmail.com` (a fullwidth letter, which mail systems deliver to `gmail.com`) is the same mailbox as `victim@gmail.com`.
- Comment email addresses must raise no RFC warnings (`email:strict`), which refuses quoted local parts (`"vic.tim"@gmail.com`), comments (`victim(c)@gmail.com`) and ideographic dots, the spellings no mailbox key can safely fold.
- The per-IP cap is checked first, and a request it refuses does not count against the mailbox cap, so a blocked client cannot use up someone else's inbox's share.
- The client IP comes from `CF-Connecting-IP` only when it holds a valid IP (new `App\Support\ClientAddress`), for the confirmation caps and the recorded `comments.ip_address`; an empty or malformed header falls back to the connecting address instead of creating a shared bucket.
- The owner check for new-comment notifications (`CommentObserver::isOwnComment`) compares mailboxes too.
- The opt-out table stores mailbox keys. It is not in production yet, so no data needs converting.

## Capabilities

### New Capabilities
<!-- None -->

### Modified Capabilities
- `comment-reply-notifications`: address comparison becomes mailbox-based throughout (including IDNA domains); confirmation emails gain a per-IP daily cap, checked first, from a validated client IP.
- `blog-comment-submission`: comment addresses must raise no RFC warnings; `ip_address` ignores a malformed forwarding header.
- `comment-notification-delivery`: the owner check compares mailboxes.

## Impact

- **Code**: `CommentEmailOptOut::normalize()` (the mailbox key), `CommentReplyEmails::requestConfirmation()` (per-IP cap), `App\Support\ClientAddress` (new), `CommentObserver` (pass the IP, mailbox owner check), `CommentReplyNotificationController::resend()` and `CommentController` (validated IP), `StoreCommentRequest` (`email:strict`), `config/comments.php` and `.env.example` (`COMMENT_REPLY_NOTIFICATIONS_CONFIRMATIONS_PER_IP_PER_DAY`).
- **Behavior**: on a mail server that does not treat `+` as a tag separator, two different addresses would share caps and an opt-out. That is only ever more conservative: it never sends more. A commenter who types a quoted or commented address (vanishingly rare) now gets a validation error.
- **Docs**: `.claude/context/comments.md`.
