## Why

Paper.li is gone. The service shut down, the site no longer uses it, and every
part of the integration left in this repo is unreachable, broken, or both:

- **The page has no route.** `resources/views/paper.blade.php` renders a
  Paper.li iframe widget, but nothing in `routes/` registers `/paper`.
  `php artisan route:list` returns no match. The template has been orphaned.
- **The harvest command cannot run.** Its signature is
  `paper:harvest {edition_id?} {{--l|limit=5}}` — the doubled braces register
  `--limit` as a *positional argument* named `{--l|limit`, so `handle()`'s
  `$this->option('limit')` throws `The "limit" option does not exist.` before
  any work begins. It never reaches its second defect: a plain-HTTP call to
  `http://paper.li/~api/papers/`, an endpoint that no longer resolves.
- **It is not scheduled.** `php artisan schedule:list` does not include it.
  Nothing invokes it.
- **CLAUDE.md documents it wrong.** It advertises a "Paper Route" that does
  not exist and a command named `harvest:paper-editions`; the real signature
  is `paper:harvest`.
- **`env()` outside config.** The command reads `env('PAPER_ID')` directly,
  which the project's own Laravel Boost guidelines prohibit. `PAPER_ID` is set
  in `.env` and appears nowhere else.

The `papers` table holds **621 real editions** spanning **2020-07-19 to
2022-04-04**, about **9.4 MB** of JSON in the `edition` column. That archive
cannot be re-harvested — the upstream service no longer exists — so it must be
exported to a file the owner has accepted before anything drops it.

Removing this now retires ~200 lines of code that cannot work, deletes a
credential-shaped `.env` entry with no consumer, and corrects three false
statements in the file that orients every future contributor.

## What Changes

- **Delete the orphaned view** `resources/views/paper.blade.php`, including the
  third-party `s3.amazonaws.com/widgets.paper.li` script tag it loads.
- **Delete the harvest command** `app/Console/Commands/HarvestPaperEditionsCommand.php`.
- **Delete the model and factory** `app/Models/Paper.php` and
  `database/factories/PaperFactory.php`.
- **Export then drop the `papers` table.** A one-time export writes all 621
  rows to a JSON file, the owner confirms the dump is intact, and only then
  does a new migration drop the table. **BREAKING** for any out-of-band
  consumer of that table; none is known to exist.
- **Remove `PAPER_ID`** from `.env`. It is absent from `.env.example`, so no
  example file changes.
- **Correct CLAUDE.md** at the three places that describe Paper.li as a live
  feature: the "Paper Route" bullet under Key Components, the
  `harvest:paper-editions` entry under Other Custom Commands, and the `Paper`
  row in the Models Reference table.

Not in scope: the privacy policy's Paper.li disclosures were already removed
in a prior change and need no further edit.

## Capabilities

### New Capabilities

None. This change removes code and data; it introduces no behavior.

### Modified Capabilities

None. Paper.li was never captured as a spec — the same situation as the
Facebook comment integration retired in `replace-facebook-comments-with-native`,
whose proposal recorded "Removed Capabilities: None — the Facebook integration
was never captured as a spec."

Because nothing on the reachable surface of the application changes — no route,
no page, no command that could previously succeed — there is no spec-level
behavior to add, modify, or delete. This change therefore sets
`skip_specs: true` in its `.openspec.yaml` rather than inventing a requirement
to satisfy validation.

## Impact

**Code removed**

| Path | Note |
| --- | --- |
| `resources/views/paper.blade.php` | Unreachable; no route registers `/paper` |
| `app/Console/Commands/HarvestPaperEditionsCommand.php` | Broken signature; dead upstream API; unscheduled |
| `app/Models/Paper.php` | Sole consumer is the deleted command |
| `database/factories/PaperFactory.php` | Referenced by no test or seeder |

**Schema** — a new migration drops `papers` (`id`, `edition_id` unique,
`edition` text, `published_at`, timestamps). The original
`2020_08_17_235254_create_papers_table.php` stays in place so the migration
history remains replayable; the new migration reverses it forward.

**Data** — 621 rows exist in the `jasonvertucio` database. The `wink` test
database has the table with **0 rows**. Per the project's documented practice,
the drop migration must be run against **both** databases.

**Configuration** — `.env:64` `PAPER_ID` is deleted. Production `.env` must be
updated at deploy time; nothing reads the value, so ordering does not matter.

**Documentation** — CLAUDE.md lines 37, 117, and 427.

**Tests** — no existing test references `Paper`, the factory, or the command,
so none breaks. A new test asserts the command is no longer registered.
