## Context

See proposal.md for the finding. Every address comparison in the feature already goes through `CommentEmailOptOut::normalize()`: the opt-out mutator and its lookups (`covers()`, `optOut()`, `lift()`), the throttle keys (`CommentReplyEmails::confirmationThrottleKey()` / `notificationThrottleKey()`), and `CommentReplyEmails::sameAddress()`, which the self-reply and owner checks use. Requirements: `specs/comment-reply-notifications/spec.md`.

## Goals / Non-Goals

**Goals:** one inbox gets one cap and one opt-out, however its address is spelled; the total number of confirmation emails one client can cause per day is bounded even across many inboxes.

**Non-Goals:** recognising every provider's alias rules (Outlook aliases, custom domains with other sub-addressing separators); a site-wide global cap.

## Decisions

### `normalize()` becomes the mailbox key
Changing the one function every comparison already uses keeps them all consistent. It lower-cases and trims, splits on the **last** `@`, drops everything from the first `+` in the local part, and for `gmail.com` / `googlemail.com` removes dots from the local part and uses `gmail.com`. If stripping would leave an empty local part (`+x@domain`), the lower-cased address is kept as is. Sending is untouched: mail goes to `$comment->email` / `replyNotificationAddress()` exactly as stored.

*Alternative considered:* a separate `mailboxKey()` used only for caps, with exact matching kept for the opt-out. Rejected: the opt-out bypass is the same bug, and two notions of "same address" would drift.

The domain is converted with `idn_to_ascii(…, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46)` before the Gmail check, because the mailer delivers `victim@ｇmail.com` and `victim@gmail。com` to `gmail.com`; without it they would get their own caps. A domain IDNA rejects is kept as given (it cannot be the same domain as a valid one). `ext-intl` is loaded locally, and `symfony/polyfill-intl-idn` is already installed, so the function exists either way.

Consequence for `sameAddress()`: `owner+x@…` counts as the owner and `v.ictim@gmail.com` replying to `victim@gmail.com` counts as a self-reply. Both withhold an email, never send one.

Consequence for `lift()`: confirming via `victim+1@gmail.com` lifts the opt-out of `victim@gmail.com`. That is right: the confirmation reached that inbox, and only its owner could press Confirm.

A trailing dot on the domain (`gmail.com.` is the fully qualified form of `gmail.com`, and `gmail.com．` or `gmail.com。` convert to it) is dropped after conversion; those spellings pass `email:strict`, so the key has to fold them. A local part that is nothing but a tag (`+a@…`) keeps its tag, but its domain is still folded.

### Refuse spellings the key cannot fold
`"vic.tim"@gmail.com` and `victim(c)@gmail.com` are valid RFC 5322 and reach `victim@gmail.com`, but folding quoted strings and comments in `normalize()` means parsing RFC 5322 by hand. Instead `StoreCommentRequest` uses `email:strict` (egulias `NoRFCWarningsValidation`), which refuses them and the ideographic dot; tagged, dotted and IDN addresses still pass. Signed-in comments take the account address instead, which bspdx.com supplies and checks only with `FILTER_VALIDATE_EMAIL` (it accepts `"vic.tim"@gmail.com`). So `requestConfirmation()` also refuses to send to an address that fails `email:strict` (`CommentReplyEmails::isPlainAddress()`), leaving that subscription pending. A verified account skips confirmation and owns its address, so it needs no guard.

*Alternative considered:* `not_regex` on `"` and `(`. Rejected: `email:strict` is the framework's own rule and also covers other warned forms.

### Counting under a lock
`takeFromCap()` takes `RateLimiter::hit()` inside `Cache::lock('lock:{key}')->block(2 s)`. Production's cache store is `file`, whose `increment()` reads and writes back without a lock, so concurrent requests with different spellings of one mailbox could all read the same count and all pass. The lock makes the read-increment-compare one step on every store that supports locks (file, database, Redis). A request that cannot get the lock in two seconds is refused, never sent uncounted. The hold is microseconds, so the wait only matters under deliberate contention.

### Per-IP daily confirmation cap
`requestConfirmation(Comment $comment, ?string $clientIp)` counts against `comment-reply-confirm-ip:{sha1(ip)}` (24 hours, `confirmations_per_ip_per_day`, default 5) as well as the address key. Both counts are taken with the existing atomic `takeFromCap()`, and the email goes only if both are within their caps. A blank IP shares one bucket, `unknown`, which is stricter, not looser. The observer passes `$comment->ip_address`; `resend()` passes the requester's IP.

The IP cap is taken first; a request it refuses returns before the mailbox cap is touched. Otherwise a client already over its own cap could keep posting with a victim's address and spend the victim's 3 a day, locking the real owner out of confirming. A send the mailbox cap refuses still spends one IP count, which only errs toward sending less.

The IP comes from the new `App\Support\ClientAddress::from()`: `CF-Connecting-IP` when it holds a valid IP (`FILTER_VALIDATE_IP`, trimmed), otherwise `$request->ip()`. A bare `header() ?? ip()` would take an empty or junk header as the address, letting a client choose its own bucket names. `resend()` and `CommentController::clientAddress()` (which records `comments.ip_address`, and so the observer's IP) use it. The other `CF-Connecting-IP ?? ip()` sites (`AppServiceProvider` limiters, `IpMiddleware`, `McpCallLogger`) are left alone here; moving them to the helper is part of the app-wide proxy work below.

## Risks / Trade-offs

- [A commenter types a quoted or commented address] → Refused with a validation error. Such addresses are vanishingly rare in a comment form; accepted.
- [An attacker under the per-IP cap can still use up a victim's mailbox share] → Three posts with the victim's address from one fresh IP spend the victim's 3 a day, and the owner cannot get a confirmation until it resets. The emails still go to the victim's own inbox, which is where the harm stops; accepted.
- [Several people behind one NAT] → The sixth opt-in from a shared IP in a day gets no confirmation email; the comment posts and the subscription stays pending. Accepted for a personal blog.
- [A mail server where `+` is part of the name] → Those addresses share caps and an opt-out. For caps and opt-outs that is more conservative. For `lift()` it is not: whoever controls `bob+x@corp.example` could confirm and so lift the opt-out of a different person at `bob@corp.example`. That needs such a server and an opted-out neighbour; accepted.
- [IP rotation still multiplies the per-IP cap] → The per-address mailbox cap still bounds what any one inbox receives (3 a day). The per-IP cap bounds what one client can spread across many inboxes.
- [The client IP can be spoofed if the origin is reachable without Cloudflare] → The IP comes from `CF-Connecting-IP` (when valid) or `ip()`, as for every other IP decision in the app (the `comments` limiter, `IpMiddleware` bans, the recorded `comments.ip_address`), and `trustProxies(at: '*')` means `ip()` alone is no safer. The per-IP cap is therefore exactly as strong as the app's IP resolution, and a spoofed IP still leaves the per-mailbox cap intact. Restricting trusted proxies to Cloudflare's ranges, or firewalling the origin, is an app-wide change outside this one.

## Migration Plan

No migration. `comment_email_opt_outs` has never been deployed. Locally, any rows in the dev/test databases from manual checks may be in the old form, and tests use transactions. Deploy is unchanged from `add-comment-reply-notifications`. The new env var is optional.
