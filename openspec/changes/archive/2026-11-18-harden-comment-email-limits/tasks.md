## 1. Mailbox comparison

- [x] 1.1 Make `CommentEmailOptOut::normalize()` the mailbox key (lower-case and trim, split on the last `@`, drop a `+tag`, Gmail/Googlemail dots and domain, keep the lower-cased address if the local part would be empty); verify with unit-style tests for each rule, including dots outside Gmail staying significant
- [x] 1.2 Verify through the feature: an opt-out covers `+tag` and Gmail spellings for reply emails; the daily confirmation cap and hourly reply cap are shared across spellings; a self-reply from another spelling and the owner's `+tag` address withhold the email; confirming a `+tag` spelling lifts the base mailbox's opt-out; mail still goes to the address as entered

## 2. Per-IP confirmation cap

- [x] 2.1 Add `confirmations_per_ip_per_day` (default 5, `COMMENT_REPLY_NOTIFICATIONS_CONFIRMATIONS_PER_IP_PER_DAY`) to `config/comments.php` and `.env.example`; verify with `php artisan config:show comments.reply_notifications`
- [x] 2.2 Give `CommentReplyEmails::requestConfirmation()` a client-IP argument counted with `takeFromCap()` alongside the address cap (blank IP shares an `unknown` bucket), pass `$comment->ip_address` from `CommentObserver` and `CF-Connecting-IP ?? ip()` from `resend()`; verify with tests: one IP over the cap across different mailboxes, a blank IP, and "Send me a new link" refused with 429 when the requester's IP is over the cap

## 3. Review hardening

- [x] 3.1 Convert the mailbox key's domain with `idn_to_ascii` (UTS #46, kept as given if IDNA rejects it), before the Gmail fold; verify with mailbox-key cases for fullwidth letters, an ideographic dot, an IDN domain and an unconvertible domain, and an opt-out covering a fullwidth spelling
- [x] 3.2 Use `email:strict` for the comment email in `StoreCommentRequest`; verify a quoted local part, a parenthesised comment and an ideographic dot are refused with nothing created or queued, and tagged/dotted/IDN addresses are accepted
- [x] 3.3 Add `App\Support\ClientAddress::from()` (valid `CF-Connecting-IP`, else `ip()`) and use it in `resend()` and `CommentController::clientAddress()`; verify valid, padded, missing, empty, blank and non-IP headers, the resend cap counting an empty header against the connecting address, and `ip_address` ignoring a non-IP header
- [x] 3.4 Take the per-IP cap first in `requestConfirmation()` and skip the mailbox cap when it refuses; verify a client over its cap cannot spend a victim mailbox's share, and "Send me a new link" is refused with 429 once the mailbox has had its share under another spelling
- [x] 3.5 Compare mailboxes in `CommentObserver::isOwnComment()`; verify the owner's `+tag` address gets no new-comment notification
- [x] 3.6 Move `GMAIL_DOMAINS` to the top of `CommentEmailOptOut` and correct its docblocks

- [x] 3.7 Second review: drop a trailing dot from the converted domain, and fold the domain of a tag-only local part; take each cap count under a cache lock (refuse on timeout), since the file store's increment is not atomic; refuse confirmations to addresses that fail `email:strict` (signed-in account addresses); canonicalise forwarded IPs; call `ClientAddress::from()` directly; verify each with a test that fails when the fix is reverted

## 4. Wrap up

- [x] 4.1 Update `.claude/context/comments.md`; run the comment test files and the full suite; run the `laravel-reviewer` agent on the diff and resolve findings
