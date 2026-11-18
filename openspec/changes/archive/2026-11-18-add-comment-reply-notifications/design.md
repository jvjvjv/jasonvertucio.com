## Context

Comments are posted through `CommentController::store()` (validated by `StoreCommentRequest`), auto-approved, and threaded through `parent_id` to a depth of 5. `CommentObserver::created()` already queues `CommentReceivedMail` to the owner and suppresses it for the owner's own comments (`comments.owner_email`). Mail goes through the `database` queue, consumed in production by a single `queue:work` worker. The comment form is the native-HTML `comment-form` Blade component, rendered once for the top level and once per reply disclosure. Visibility is decided by `Comment::isVisible()` ("approved, not spam, not trashed"), and comment routes 404 for unpublished posts (`Comment::isOnPublishedPost()`). Nothing in the app uses signed URLs yet.

Anonymous commenters' addresses are unverified, which is why the user chose double opt-in (see proposal.md, Why). Requirements are in `specs/comment-reply-notifications/spec.md`.

## Goals / Non-Goals

**Goals:**
- Keep every recipient decision at send time, in one place, so spam, deletion and unsubscribe during the delay are always respected.
- No GET request changes subscription state, so mail scanners that prefetch links cannot confirm or unsubscribe anyone.
- Bound outbound mail per address, so the feature cannot be turned against an inbox.

**Non-Goals:**
- A signed-in "my subscriptions" page, or toggling a subscription from the post after posting.
- Notifying ancestors beyond the direct parent, or "follow this post" subscriptions.
- Batched or digest emails.
- Replying to a comment by replying to the email.
- Changing the owner's `CommentReceivedMail`.

## Decisions

### Subscription state lives on the comment
Two columns on `comments`: `notify_replies` (boolean, default false) and `notify_replies_confirmed_at` (nullable timestamp). A subscription is **active** when both are set. Opt-ins from a signed-in user whose account address is verified (`User::hasVerifiedEmail()`, i.e. `email_verified_at` set) get `notify_replies_confirmed_at = now()` at creation; everyone else, anonymous or signed in without a verified address, gets it when the confirm button is pressed. Signing in is not treated as verification: `.claude/context/auth.md` says bspdx.com's `email_verified` is not used, and `BspdxAccountResolver::syncEmail()` clears `email_verified_at` when the address changes at bspdx.com. Unsubscribing one comment sets `notify_replies = false` and leaves the timestamp as history. `notify_replies` is `$fillable`; `notify_replies_confirmed_at` is not (the same treatment as `edited_at`), so it can only be set deliberately. A `Comment::hasActiveReplySubscription()` method and a `Comment::replyNotificationAddress()` method (the account's current address while `hasVerifiedEmail()`, otherwise the address recorded on the comment, which is what was confirmed) keep the rules in one place.

*Alternative considered:* a separate `comment_reply_subscriptions` table. It adds a join and a model for a strictly one-to-one fact, so it was rejected.

*Alternative considered:* confirming per **address** rather than per comment, so an address confirmed once is trusted for later opt-ins. That would let anyone subscribe a confirmed address to comments its owner never wrote, which is what double opt-in exists to prevent. Per-comment confirmation costs a returning anonymous commenter one click per opted-in comment, which is acceptable for a personal blog.

### Address-wide opt-out is its own table
`comment_email_opt_outs` (`id`, `email` unique, `created_at`), with a `CommentEmailOptOut` model and a `CommentEmailOptOut::covers(string $email): bool` lookup. Addresses are stored and compared lowercased (normalized by a mutator, so the unique index enforces it). It is checked before every reply email; it does not block confirmation emails, so an opted-out address can opt in again (see *Opting back in*). It is separate from the per-comment flag because it must also cover comments that do not exist yet.

### The observer decides what to queue; a job decides what to send
`CommentObserver::created()` gains two branches after the existing owner notification, both skipped when `comments.reply_notifications.enabled` is false:

1. **Confirmation:** the comment opted in and is not confirmed (anonymous, or signed in without a verified address) → if the per-address daily cap allows it, queue `CommentReplyConfirmationMail`. An opted-out address is **not** skipped: the opt-out is lifted only when that confirmation is completed (see *Opting back in*). The cap check and the queueing live in `CommentReplyEmails::requestConfirmation()`, shared with the new-link button.
2. **Reply:** the comment has a `parent_id` → dispatch `NotifyCommentReplyRecipient($reply)` with `->delay(now()->addMinutes(config(...delay_minutes)))`.

`NotifyCommentReplyRecipient::handle()` reloads the reply with `withTrashed()` and its parent (also `withTrashed()`) and the post, then runs every check in the spec's "withheld" requirement, then the per-address hourly cap, then sends `CommentReplyMail` synchronously inside the job. The job, not the mailable, is queued, because a queued mailable cannot decline to send once it is dispatched.

*Alternative considered:* checking everything in the observer and queueing the mailable with `later()`. It would miss anything that changes during the delay (spam, deletion, unsubscribe), which is the main reason the delay exists.

The delay defaults to 10 minutes. With the database queue, `available_at` holds the job until then; under `sync` (tests) the delay is ignored and the job runs at once, so tests mark the reply as spam before the job runs by calling `handle()` directly rather than relying on the delay.

### Per-address caps use the cache rate limiter
Both caps use `RateLimiter::attempt()` keyed on `sha1(lowercased address)` (no address in a cache key or file name): `comment-reply-confirm:{hash}` with a 24-hour decay, and `comment-reply-notify:{hash}` with a 1-hour decay. These are fixed windows starting at the first hit rather than strictly rolling ones; that is precise enough for abuse limiting. A capped send is logged at `info` with the comment ids and no address.

*Alternative considered:* counting sent emails in a log table. More exact, but it is a new table kept only for counting.

### Signed, relative URLs and button-press pages
Five routes in `routes/blog.php`, handled by a new `CommentReplyNotificationController`, all behind `signed:relative`:

| Method | Path | Name | Does |
|---|---|---|---|
| GET | `/comments/{comment}/reply-emails/confirm` | `comments.reply-emails.confirm` | Page with a Confirm button |
| POST | `/comments/{comment}/reply-emails/confirm` | `comments.reply-emails.confirm.store` | Sets `notify_replies_confirmed_at` if the comment is visible and still opted in |
| GET | `/comments/{comment}/reply-emails/unsubscribe` | `comments.reply-emails.unsubscribe` | Page with two buttons |
| POST | `/comments/{comment}/reply-emails/unsubscribe` | `comments.reply-emails.unsubscribe.store` | `scope=comment` (the default) clears `notify_replies`; `scope=address` also writes the opt-out |
| POST | `/comments/{comment}/reply-emails/resend` | `comments.reply-emails.resend` | Emails a fresh confirmation link (see *Expired and invalid links*) |

Relative signatures (`URL::temporarySignedRoute(..., absolute: false)` / `URL::signedRoute(..., absolute: false)`, wrapped in `url()` for the email) avoid signature failures from scheme or host differences behind Cloudflare and the proxy. The confirm link expires after `confirmation_expires_days` (default 7); the unsubscribe link does not expire, as unsubscribe links must keep working.

Each GET page's form posts back to the URL it was loaded from (`request()->getRequestUri()`), so the signature travels with the POST. The confirm POST keeps CSRF protection. The unsubscribe POST is added to `preventRequestForgery(except: [...])` because RFC 8058 one-click unsubscribe is a token-less POST from the mail provider; the signature is what authorizes it. The default `scope=comment` is the one-click behavior.

The pages extend the site `layout` and use the existing comment-form styling (flat, no gradients or shadows). Their copy names the post and, for unsubscribe, never prints the address in full. A confirmed or unsubscribed result renders on the same page rather than redirecting to the post, since the post may have been unpublished.

The `{comment}` binding resolves `withTrashed()` for the unsubscribe routes, so an unsubscribe link still works after the comment is deleted. The confirm routes do not, so a deleted comment's confirm link 404s.

### Opting back in
The opt-out is not permanent. It is lifted (`CommentEmailOptOut::lift()`) at exactly two points, both of which prove the address owner asked: when a signed-in user with a verified account address opts in (`CommentController::store()`), and when Confirm is pressed (`CommentReplyNotificationController::confirm()`, lifting the comment's recorded address, which is where the confirmation went). Ticking the box without a verified address does **not** lift it by itself; it only sends a confirmation email, still under the daily cap. The confirmation email for an opted-out address tells the reader that confirming turns comment emails back on.

*Alternatives considered:* a "turn emails back on" button on the unsubscribe page (rejected by the user in favor of opting in again), and an opt-out that expires on its own.

### Expired and invalid links
`signed:relative` throws `InvalidSignatureException` (403). `bootstrap/app.php` renders it, for routes named `comments.reply-emails.*` only, through `CommentReplyNotificationController::invalidLink()`, keeping the 403 status. That method distinguishes the cases with `URL::hasCorrectSignature($request, false)` and `URL::signatureHasNotExpired($request)`:

- **Genuine but expired confirm link:** an "expired" page. Route-model binding has already resolved `{comment}`, so the page can check the comment: still pending → a "Send me a new link" button; already confirmed → "already on"; hidden or no longer opted in → "unavailable".
- **Missing or altered signature:** a "not valid" page with no button.

The button posts to a fifth route, `POST /comments/{comment}/reply-emails/resend` (`comments.reply-emails.resend`), signed with a fresh one-hour `temporarySignedRoute` generated when the expired page is rendered. It cannot be reached from an expired link directly, only from a page the server rendered after verifying that link's signature. It keeps CSRF, re-checks that the comment is visible, opted in and unconfirmed, then calls `CommentReplyEmails::requestConfirmation()`; over the cap it answers 429 with a "try again later" page.

### The reply email is an HTML view, not Markdown mail
`CommentReplyMail` uses `Content(view: 'mail.comment-reply.html', text: 'mail.comment-reply.text')` (the folder layout of the existing `mail/follow-up` views). The existing `new-comment` template uses `<x-mail::message>`, which parses Markdown, so a `[link](...)` in a reply would become a live link in mail sent to a third party under the site's name. The HTML view prints the reply as `nl2br(e($reply->message))` in a `<blockquote>`; the text view prints it as is. The subject is `"{replier} replied to your comment on {post title}"`. Headers are added in `headers()`: `List-Unsubscribe: <{one-click url}>` and `List-Unsubscribe-Post: List-Unsubscribe=One-Click`. The confirmation mail is built the same way, with a Confirm link and a "Didn't ask for this?" unsubscribe link.

### Form field and request
`comment-form` gets a checkbox named `notify_replies`, rendered only when `config('comments.reply_notifications.enabled')`, restored from `old('notify_replies')` only when `$isTarget` (the same rule the other fields follow). The checkbox gets an id suffixed like the others (`notify-replies-{parentId|root}`). `StoreCommentRequest` adds `'notify_replies' => ['nullable', 'boolean']`, and the controller stores `$request->boolean('notify_replies') && config(...enabled)`, plus `notify_replies_confirmed_at = now()` for a signed-in user who opted in. Honeypot-discarded submissions create nothing and so send nothing.

### Configuration
`config/comments.php` → `reply_notifications`: `enabled` (`COMMENT_REPLY_NOTIFICATIONS_ENABLED`, true), `delay_minutes` (10), `confirmation_expires_days` (7), `confirmations_per_address_per_day` (3), `notifications_per_address_per_hour` (5). Each is read from `env()` only inside the config file.

## Risks / Trade-offs

- [The confirmation email is itself a mail-to-anyone vector] → One per opted-in comment or new-link request, capped at 3 per address per day, and the comment rate limiter (5 a minute per IP) already applies. The email carries an opt-out link.
- [An opted-out address can be sent confirmation emails again by anyone who types it] → Accepted by the user in exchange for a non-permanent opt-out. Still bounded by the 3-a-day cap, and nothing beyond the confirmation email is sent until the address owner confirms.
- [The worker in production is `queue:work` and holds old code] → The deploy steps restart it through `supervisorctl`, per CLAUDE.md.
- [A delayed job outlives the reply's state] → The job reloads everything with `withTrashed()` and re-checks; a missing reply or parent row is a silent no-op, not a failure.
- [Fixed-window caps can allow up to twice the cap across a window boundary] → Acceptable for abuse limiting; the numbers are configurable.
- [Signed URL behaviour behind Cloudflare] → Relative signatures. A feature test exercises a URL generated by the mailable end to end through the middleware.
- [Local mail sends for real (Mailjet)] → Manual checks use `MAIL_DRIVER=log`; tests use `Mail::fake()` / `Queue::fake()`.
- [A signed-in user whose account is deleted] → If the foreign key nulls `user_id`, the comment falls back to its snapshot address. That address was the verified account address at posting time, so continuing to honour the opt-in is acceptable; the unsubscribe link still works.

## Migration Plan

1. One migration: add the two `comments` columns and create `comment_email_opt_outs`. `down()` drops both. Existing rows default to `notify_replies = false`, so no existing commenter is subscribed.
2. Run locally against both databases: `php artisan migrate` and `DB_DATABASE=wink php artisan migrate`.
3. **Production (developer-owned):** deploy, run `php artisan migrate`, `php artisan optimize:clear`, then `sudo supervisorctl restart <worker program>` so the worker loads the new job class.
4. Rollback: set `COMMENT_REPLY_NOTIFICATIONS_ENABLED=false` (hides the checkbox and stops all sends) or roll back the migration. Delayed jobs already queued then no-op through the enabled check in `handle()`.
