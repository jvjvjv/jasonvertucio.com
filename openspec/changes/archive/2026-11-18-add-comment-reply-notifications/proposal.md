## Why

A commenter who gets a reply has no way of knowing unless they come back and look. Threaded comments only become conversations when the person being answered finds out, so a commenter who asks for it should get an email when someone replies to them. Comment emails are unverified for anonymous visitors, so the feature must not let anyone point the site's mail at an address they don't own.

## What Changes

- Every comment form gets an **"Email me when someone replies"** checkbox, unchecked by default, for anonymous and signed-in commenters alike.
- **Signed-in commenters with a verified account address** (`email_verified_at` set on this site) are subscribed as soon as they post with the box checked. Signing in alone is not proof: this site does not rely on bspdx.com's `email_verified`, and an email change made at bspdx.com clears `email_verified_at` here.
- **Everyone else** (anonymous visitors and signed-in users without a verified address) must confirm first (double opt-in). Checking the box queues a confirmation email with a signed link; no reply email goes to that address for that comment until the link is followed and the confirmation button pressed. Confirmation emails are capped per address per day.
- When a reply is posted **directly** to a subscribed comment, the comment's author gets an email naming the replier, quoting the reply as plain text and linking to it on the post. Replies further down the thread notify their own direct parent instead, not every ancestor.
- The reply email is **delayed a few minutes and re-checked when it sends**: nothing goes out if the reply or the parent has been marked spam or deleted, the post is no longer published, the replier is the subscriber, or the subscriber is the site owner (who already gets the new-comment notification).
- Reply emails to one address are **capped per hour**, so a burst of replies can't flood an inbox.
- Every email (confirmation and reply) carries a **signed unsubscribe link**. Its page offers "stop emails for this comment" and "stop all comment emails to this address". Reply emails also carry `List-Unsubscribe` / `List-Unsubscribe-Post` headers for one-click unsubscribe from the mail client.
- An address-wide **opt-out list** records addresses that want no more comment emails, which covers someone who never asked for the confirmation email in the first place. The opt-out is **not permanent**: a new opt-in from that address gets a confirmation email again (still capped), and confirming it, or opting in from a verified account, lifts the opt-out.
- An **expired or tampered link** shows a page that says so (still a 403) instead of a bare error. An expired confirmation link offers a "Send me a new link" button, counted against the same daily cap.
- New config under `comments.reply_notifications` (enabled toggle, send delay, confirmation link lifetime, per-address caps).

## Capabilities

### New Capabilities
- `comment-reply-notifications`: Opt-in, double-opt-in for anonymous commenters, email notification of direct replies to a commenter, the send-time checks, rate caps, unsubscribe and the address-wide opt-out.

### Modified Capabilities
<!-- None. The owner's new-comment notification (comment-notification-delivery) and comment submission rules are unchanged; the checkbox is an extra optional field covered by the new capability. -->

## Impact

- **Database**: migration adding `notify_replies` and `notify_replies_confirmed_at` to `comments`, and a new `comment_email_opt_outs` table. Must run against both the app DB and the `wink` test DB.
- **Code**: `StoreCommentRequest` and `CommentController::store()` (new field), `CommentObserver::created()` (confirmation and reply dispatch), new queued job for the reply email, two new mailables and their views, a new controller with signed confirm/unsubscribe routes, a `CommentEmailOptOut` model, the `comment-form` Blade component, `config/comments.php`, and a CSRF exemption for the one-click unsubscribe POST in `bootstrap/app.php`.
- **Public pages**: two small new public pages (confirm, unsubscribe) in the site's existing look.
- **Mail and queue**: more outbound mail to third-party addresses, through the existing database queue and the production `queue:work` worker. The worker must be restarted on deploy.
- **Docs**: `.claude/context/comments.md` and the *Queues* section of `CLAUDE.md`.
