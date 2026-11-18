## Context

`CommentEmailOptOut::normalize()` (the mailbox key) drops a `+tag` on every domain so that rotating tags cannot get extra confirmation emails or slip past an opt-out. Before this change the opt-out table stored only that key, so `covers()` and `lift()` both used it. Blocking with a broad key errs toward sending less; lifting with it lets an address that is merely *similar* undo someone else's opt-out.

## Goals / Non-Goals

**Goals:** only the address that opted out (or a spelling certain to reach the same inbox) can lift its opt-out; blocking and the caps keep the broad key.

**Non-Goals:** changing the caps or self-reply/owner checks (they withhold, so broad is right); a complete registry of subaddressing providers.

## Decisions

### Two keys per row
`email` holds `addressKey()` (unique), `mailbox` holds `normalize()` (indexed). The model's `email` mutator sets both, so every write path stays consistent. `covers()` matches `mailbox`; `lift()` deletes by `email`; `optOut()` is `createOrFirst` on `email`.

*Alternative considered:* fold `+tags` only for known providers in the single key. Rejected: on custom domains that do support `+` (many Google Workspace and Fastmail domains) tag rotation would again get separate caps and slip past opt-outs.

### `addressKey()`
Same as the mailbox key (lower-case, IDNA domain without trailing dot, Gmail dots and googlemail fold) except the `+tag` is dropped only on `SUBADDRESSING_DOMAINS`. So Gmail users who opted out from a tagged spelling can still lift it from the plain one, and vice versa.

### Telling the person when a lift was not enough
`coveredByAnotherAddress()` is true when a row matches the mailbox but not the address key. The confirm page then shows `confirmed-blocked` (subscription confirmed, but the address won't get emails until it opts in from that other spelling), and the confirmation email omits "confirming turns them back on". A verified signed-in opt-in has no page to say it on; it is the same rare case and still sends nothing.

## Risks / Trade-offs

- [A person on an unlisted domain opted out from a tagged spelling and later confirms from the plain one] → They stay opted out and the page says how to undo it. Sending nothing is the safe failure.
- [The provider list goes stale] → An unlisted provider only gets the stricter behaviour above; nothing is sent that should not be.

## Migration Plan

New migration: add nullable `mailbox`, backfill from `email` with `normalize()`, then make it non-null and index it. Existing (local-only) rows were stored by mailbox key, so their `email` stays as is. Run on both databases (`jasonvertucio` and `wink`). Rollback drops the column and index.
