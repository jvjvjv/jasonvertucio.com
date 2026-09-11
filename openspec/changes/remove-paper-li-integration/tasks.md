## 1. Export the archive

- [ ] 1.1 Create `storage/app/archives/` and confirm `git check-ignore -v storage/app/archives/papers-2026-09-08.sql` reports the path as ignored by `storage/app/.gitignore`
- [ ] 1.2 Run `mysqldump --no-create-db jasonvertucio papers > storage/app/archives/papers-2026-09-08.sql` and verify the file is non-empty and contains an `INSERT INTO \`papers\`` statement
- [ ] 1.3 Restore the dump into a scratch database and verify `SELECT COUNT(*) FROM papers` returns **621** and `SELECT SUM(LENGTH(edition)) FROM papers` returns **9393037**, matching the live table
- [ ] 1.4 Spot-check recovery by `json_decode`-ing the `edition` column of the earliest (2020-07-19) and latest (2022-04-04) restored rows and verifying both parse without error
- [ ] 1.5 Record `sha256sum storage/app/archives/papers-2026-09-08.sql` and report the path, checksum, and the three verification numbers to the owner

## 2. Owner confirmation gate

- [ ] 2.1 **STOP.** Wait for the owner to confirm they have inspected the dump and hold a copy off-server. Do not proceed to group 4 without an explicit go-ahead — group 4 is irreversible and Paper.li no longer exists to re-harvest from

## 3. Remove the code

- [ ] 3.1 Delete `resources/views/paper.blade.php` and verify no Blade template, controller, or route file references `paper` via `grep -rni "paper" resources/views routes app --include=*.php --include=*.blade.php` (expect hits only in the files scheduled for deletion below)
- [ ] 3.2 Delete `app/Console/Commands/HarvestPaperEditionsCommand.php` and verify `php artisan list | grep -i paper` returns nothing
- [ ] 3.3 Delete `database/factories/PaperFactory.php` and `app/Models/Paper.php`, and verify `grep -rn "Paper\b" app database tests --include=*.php` returns no hits outside `Resume*` class names
- [ ] 3.4 Run `vendor/bin/phpunit` and verify all 459 tests still pass — no existing test references the model, factory, or command

## 4. Drop the table

- [ ] 4.1 Create the drop migration with `php artisan make:migration drop_papers_table --no-interaction`; `up()` calls `Schema::dropIfExists('papers')`, `down()` recreates the original structure (`id`, `edition_id` string unique, `edition` text, `published_at` timestamp, `timestamps`) with a comment stating that rollback restores structure only and never rows
- [ ] 4.2 Leave `database/migrations/2020_08_17_235254_create_papers_table.php` untouched, and verify the two migrations sort create-then-drop by filename so a from-scratch replay stays valid
- [ ] 4.3 Run `php artisan migrate` against `jasonvertucio` and verify `Schema::hasTable('papers')` returns false
- [ ] 4.4 Run `DB_DATABASE=wink php artisan migrate` and verify the same against the test database, per this project's practice of migrating both

## 5. Configuration and documentation

- [ ] 5.1 Delete the `PAPER_ID` line from `.env` (line 64) and verify `grep -rn "PAPER_ID" . --exclude-dir=vendor --exclude-dir=node_modules --exclude-dir=.git --exclude-dir=openspec` returns nothing
- [ ] 5.2 Remove the "**Paper Route**: Static page for paper-related content" bullet from CLAUDE.md's Key Components list (line 37)
- [ ] 5.3 Remove the `php artisan harvest:paper-editions` entry from CLAUDE.md's Other Custom Commands block (line 117) — note the documented name was wrong; the real signature was `paper:harvest`
- [ ] 5.4 Remove the `Paper | Paper editions` row from CLAUDE.md's Models Reference table (line 427), and verify `grep -ni "paper" CLAUDE.md` returns no hits other than resume-related wording

## 6. Lock in the removal

- [ ] 6.1 Create `tests/Feature/PaperLiRemovalTest.php` asserting `Artisan::all()` has no key matching `paper:harvest`, and verify the test fails if the command file is restored
- [ ] 6.2 Add an assertion to the same test that `GET /paper` returns 404, locking in behavior that is currently true only because no route was ever registered
- [ ] 6.3 Run `vendor/bin/phpunit --filter=PaperLiRemoval` and verify both assertions pass
- [ ] 6.4 Run the full suite `vendor/bin/phpunit` and verify the pass count is 459 plus the new test's cases, with no failures

## 7. Deploy

- [ ] 7.1 Remove `PAPER_ID` from the production `.env` and verify the application boots — nothing reads the value, so this cannot break the deploy
- [ ] 7.2 Run `php artisan migrate` on production and verify `papers` is gone and `php artisan optimize:clear` completes without error
