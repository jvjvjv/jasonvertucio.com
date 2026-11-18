## Why

A security review of `harden-comment-email-limits` found that the mailbox key it introduced drops a `+tag` on every domain, and that the same key decided who may lift an opt-out. On a mail server where `+` is part of the name, `bob+x@corp.example` and `bob@corp.example` are different people, yet whoever controls `bob+x` could confirm a subscription and so lift `bob`'s opt-out, restoring reply emails `bob` had stopped. Blocking with the broad key only ever sends less, but lifting with it lets one person undo another's choice.

## What Changes

- An opt-out row keeps two keys: `email`, the **address key** of the address that opted out, and `mailbox`, the broad **mailbox key** (new column, indexed).
- **Blocking stays broad**: an opt-out still blocks every spelling with the same mailbox key.
- **Lifting is exact**: confirming, or a verified signed-in opt-in, lifts only the opt-out made from the same address key. The address key drops a `+tag` (and Gmail dots) only on providers known to deliver tagged mail to the same inbox (Gmail/Googlemail, Outlook/Hotmail/Live/MSN, iCloud/me/mac, Fastmail, Proton); elsewhere the tag is kept.
- When a confirmed subscription is still blocked by an opt-out from another spelling, the confirm page says so ("This address won't get them yet …") instead of promising emails, and the confirmation email does not claim that confirming turns emails back on.

## Capabilities

### New Capabilities
<!-- None -->

### Modified Capabilities
- `comment-reply-notifications`: lifting an opt-out is limited to the address that made it; mailbox comparison no longer decides lifts; the confirm page reports a subscription still blocked by another spelling.

## Impact

- **Code**: `CommentEmailOptOut` (`addressKey()`, `coveredByAnotherAddress()`, `covers()`/`lift()`/`optOut()`, mutator), `CommentReplyNotificationController::confirmPage()`, `CommentReplyConfirmationMail`, `comment-reply-emails/confirm.blade.php`.
- **Database**: new migration adding `comment_email_opt_outs.mailbox` (indexed, backfilled). The table has never held production data.
- **Behavior**: someone at an unknown domain who opted out as `bob+news@…` and later confirms as `bob@…` stays opted out until they confirm from `bob+news@…`; the page tells them. On the listed providers nothing changes.
- **Docs**: `.claude/context/comments.md`.
