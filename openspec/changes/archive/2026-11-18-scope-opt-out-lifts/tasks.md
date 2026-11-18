## 1. Two keys per opt-out

- [x] 1.1 Add a migration for `comment_email_opt_outs.mailbox` (nullable, backfill with `normalize()`, then non-null and indexed); run it on `jasonvertucio` and `wink`, and check rollback on `wink`
- [x] 1.2 Add `CommentEmailOptOut::addressKey()` (tag dropped only on known subaddressing providers) and have the `email` mutator set `email` and `mailbox`; `covers()` matches `mailbox`, `lift()` and `optOut()` use `email`; verify with address-key cases and storage/lift tests

## 2. Saying when a lift was not enough

- [x] 2.1 Add `coveredByAnotherAddress()`; the confirm page shows a `confirmed-blocked` state, and the confirmation email omits "confirming turns them back on" when it applies; verify with tests and a screenshot of the new state

## 3. Wrap up

- [x] 3.1 Update `.claude/context/comments.md`; run the comment tests and the full suite; mutation-check the new rules
