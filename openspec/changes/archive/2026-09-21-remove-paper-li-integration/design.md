## Context

See `proposal.md` — Why. The design-relevant facts:

- The `papers` table holds 621 rows, ~9.4 MB of JSON, spanning 2020-07-19 to
  2022-04-04, in the `jasonvertucio` database only. The `wink` test database
  has the table with 0 rows.
- Paper.li no longer exists, so the archive **cannot be regenerated**. This is
  a one-way door, and it is the only part of this change that is.
- Everything else being removed is unreachable code: no route resolves to the
  view, and the harvest command throws on its own malformed signature before
  it reaches its dead upstream endpoint.
- `storage/app/` is git-ignored via `storage/app/.gitignore` (`*`, with only
  `public/` and the ignore file itself excepted), so it can hold an export
  without the 9.4 MB landing in version control.
- This project's documented practice is to run migrations against both the
  `jasonvertucio` and `wink` databases.

## Goals / Non-Goals

**Goals:**

- Make the archive recoverable from a file the owner has inspected and stored
  off-server *before* any statement drops the table.
- Leave the migration history replayable from scratch.
- Remove the code in a way that a test can prove, not just a diff.

**Non-Goals:**

- Preserving any ability to read the archive through the application. After
  this change, the 621 editions live in a file, not in a queryable feature.
  Building a viewer for them would be a separate change.
- Rewriting or squashing `2020_08_17_235254_create_papers_table.php`.
- Touching the privacy policy, which no longer mentions Paper.li.

## Decisions

### D1: Export with `mysqldump`, not an Artisan/JSON exporter

`mysqldump --no-create-db jasonvertucio papers` produces a file that restores
with a single `mysql <` command, preserves exact column types and the
`edition_id` unique index, and — critically — **does not depend on
`App\Models\Paper`**, which this same change deletes. The export step is
therefore independent of the code-removal step and cannot be broken by it.

*Alternatives considered.* An Artisan command writing JSON would be more
human-readable, but it would have to run before the model is deleted, coupling
the two steps, and it would silently lose the schema. Copying the table to
`papers_backup` inside the same database was rejected outright: it leaves the
data on the same disk with the same failure modes, which is not a backup.

### D2: A forward drop migration; the original create migration stays

Add a new timestamped migration whose `up()` drops `papers` and whose `down()`
recreates the empty table with the original column definitions. Leaving
`2020_08_17_235254_create_papers_table.php` untouched keeps a from-scratch
`migrate` replayable in timestamp order — create, then drop — which matters
because this project's migration chain is already known to be fragile.

The `down()` deliberately restores **structure only**. It cannot restore rows,
and the migration file will say so in a comment so nobody mistakes a successful
rollback for recovered data. Recovery means restoring the dump from D1.

### D3: The export is gated on the owner's confirmation

The drop does not run in the same session as the export. The task list makes
this an explicit stop: produce the dump, verify it independently (row count
and a spot-check of one `edition` payload restored into a scratch database),
report the path and checksum to the owner, and wait. Only an explicit
go-ahead releases the drop.

This costs one round trip and removes the only irreversible risk in the change.

### D4: Prove the removal with a test, not just absence of files

Add a feature test asserting `paper:harvest` is absent from the registered
Artisan commands and that `GET /paper` returns 404. Asserting a file does not
exist proves nothing durable; asserting the command is unregistered catches a
future accidental re-add, and the route assertion locks in what is currently
true only by omission.

### D5: Order of operations

Export and confirm → remove code (view, command, model, factory) → drop
migration on both databases → `.env` → CLAUDE.md → test. Code removal is
placed before the drop because the model is unused by the `mysqldump` path,
and doing it first means a failed drop leaves no half-wired code behind.

## Risks / Trade-offs

| Risk | Mitigation |
| --- | --- |
| The dump is corrupt or truncated, and nobody notices until after the drop | Verify by restoring into a scratch database and comparing `COUNT(*)` = 621 and `SUM(LENGTH(edition))` = 9,393,037 against the live table before the drop. Both numbers are recorded in the proposal. |
| The dump stays only on the server, which then fails | D3 requires the owner to confirm they hold a copy off-server. The export lands in git-ignored `storage/app/`, which is not backed up by version control. |
| An unknown external consumer reads the `papers` table | No route, model reference, factory use, seeder, or test outside the deleted files touches it. Anything outside the repo is invisible to this audit — flagged as **BREAKING** in the proposal. |
| The `wink` test database drifts from `jasonvertucio` | Run the migration against both, per project practice. `wink` has 0 rows, so its drop is trivially safe. |
| Production `.env` retains `PAPER_ID` after deploy | Harmless — nothing reads it once the command is gone. Removing it is hygiene, not a correctness requirement, so it cannot block the deploy. |
| Someone later wants the editions back as a feature | The dump restores in one command. Rebuilding a viewer is a new change with the data already in hand. |

## Migration Plan

1. `mysqldump --no-create-db jasonvertucio papers > storage/app/archives/papers-2026-09-08.sql`
2. Verify against a scratch database; record `sha256sum`.
3. **Stop.** Owner confirms the dump and an off-server copy.
4. Merge the code removal and the drop migration.
5. Deploy: `php artisan migrate`, then `DB_DATABASE=wink php artisan migrate`.
6. Remove `PAPER_ID` from production `.env`.

**Rollback.** Before step 4, nothing has changed. After step 4, `php artisan
migrate:rollback` restores the empty table and `git revert` restores the code;
the rows come back only by restoring the dump from step 1.
