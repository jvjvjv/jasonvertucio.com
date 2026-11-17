## Context

- **Applications** (`app/Models/Application.php`) already carry a dated status history (`statusUpdates`, ordered by `occurred_at`, with `latestStatusUpdate`). `ApplicationService` owns every lifecycle write in a transaction. Status-history entries go through `ApplicationStatusUpdateController` under `/api/admin/employment/applications/{application}/status-updates`, with Form Requests and `ApplicationPayload` answers. This change copies that shape for contacts and follow-ups.
- **One-shot AI generation** already exists in `JobUrlParseService`: `AgentFactory::forSystem($system, instructions:, maxTokens:)`, then `$agent->prompt($text)->text`. `AiSystem::defaultForFeature($key)` resolves a feature default. Feature keys are hardcoded in `ValidatesAiSystemRole::FEATURE_ROLES` and in `CHAT_FEATURES` and the prompt lock in `resources/js/admin/pages/ai/systems/Form.tsx`.
- **Built-in prompts** are rows in `ai_system_prompts` with fixed ids. Ids 1–6 are used and 1–49 are reserved. `2026_10_24_115901_seed_company_intel_prompt.php` is the pattern: refuse to overwrite, take content from a service builder, real `down()`.
- **Mail** goes out over SMTP (Mailjet) from `me@jasonvertucio.com`. `CommentReceivedMail` is the only queued mailable. `QUEUE_CONNECTION=database` with a supervisor-managed `queue:work`. `routes/console.php` already registers scheduled commands.
- **Tests** run against the `wink` database with `DatabaseTransactions` (never `RefreshDatabase`). `JobUrlParseTest` shows how to mock `AgentFactory`.

## Goals / Non-Goals

**Goals:**

- No email reaches a third party without an explicit approve action on that specific draft.
- Every write is one transaction, and every concurrent path (scheduler, approve, status change) leaves exactly one coherent state.

**Non-Goals:**

- No reading of replies, inbox integration, or threading (`In-Reply-To`). Replies land in my normal inbox.
- No "Redraft" or "Draft now" button. Discarding a draft lets the next scheduled run draft again a week later. Either is easy to add later.
- No per-contact opt-out, no per-application cadence or maximum (both are site-wide config), no scheduling a send for later.
- No contacts on the New Session page. They are added on the Discussion page after the application exists.
- No change to the AI session's tools or prompt. The session never sees contacts.

## Decisions

### Data model: two child tables and a flag

- `application_contacts`:
  - `id`, `application_id` (FK, cascade on delete);
  - `email` (string 254), `name`, `role`, `notes` (all nullable);
  - timestamps;
  - unique (`application_id`, `email`).
  - Email is lower-cased on save, so the unique index enforces the case-insensitive rule, and the Form Request checks it too so the user gets a 422 rather than a database error.
- `application_follow_ups`:
  - `id`, `application_id` (FK, cascade);
  - `status`, a `FollowUpStatus` enum: `pending`, `sending`, `sent`, `failed`, `discarded`;
  - `subject`, `body` (text);
  - `recipients` (json, null until approved);
  - `ai_system_id` (nullable, null on delete);
  - `drafted_at`, `sent_at`, `discarded_at`;
  - `discard_reason` (`manual`, `superseded`, `disabled`);
  - `failure_reason` (text);
  - timestamps.
  - Index on (`application_id`, `status`).
- `applications.follow_ups_enabled` (boolean, default false).

Contacts and follow-ups are separate tables, rather than a json column on applications, so the unique rule, per-row editing and the "recipients are a snapshot" rule are all plain relational behaviour. A soft-deleted application keeps its rows, and the scheduler simply never selects it.

### Due check: one query, cadence from config

`ApplicationFollowUpService::dueApplications()`:

1. Selects non-deleted applications with `follow_ups_enabled`, a status in the three follow-up statuses, `whereHas('contacts')` and `whereDoesntHave` a pending or sending follow-up. These conditions are in SQL.
2. Filters in PHP, which is cheap because volume is tiny:
   - **Cap:** the count of `sent` follow-ups with `sent_at` after the latest status-history entry's `created_at` is below `max_per_status`.
   - **Cadence:** `max(latestStatusUpdate.occurred_at, last sent follow-up's sent_at) <= now() - cadence`.

`config/resume.php` gains:

```php
'follow_ups' => [
    'cadence_days' => (int) env('RESUME_FOLLOW_UP_CADENCE_DAYS', 7),
    'max_per_status' => (int) env('RESUME_FOLLOW_UP_MAX_PER_STATUS', 3),
    'notification_email' => env('RESUME_FOLLOW_UP_NOTIFICATION_EMAIL', 'me@jasonvertucio.com'),
],
```

The three statuses are a new `ApplicationStatus::receivesFollowUps()`: an explicit `match` returning true for `Applied`, `Interviewed` and `Offered`. `Interviewing` is excluded because the employer is actively engaged and a "just checking in" mail would be noise. The terminal statuses are excluded because the process is over. Using an explicit `match` with no `default` arm means a status added later must be classified deliberately.

Two timestamps play different roles:

- **Cadence** is measured from the entry's `occurred_at`, the date the event happened. A backdated "interviewed two weeks ago" entry can therefore make the application due straight away, which is correct.
- **The cap's reset** is measured from the entry's `created_at`, when the status change was recorded in the system. Backdating an entry can't retroactively un-count follow-ups that were already sent, and the count resets exactly when the admin logs a new status.

Deleting the latest entry makes the previous one the latest, so follow-ups sent since that earlier entry count again. That's the right answer for a correction.

Only `sent` follow-ups count toward the cap. Discarded and failed ones don't, because nothing reached the contact.

### Scheduling: a daily command that fans out queued jobs

`applications:draft-follow-ups` runs `dailyAt('09:00')->withoutOverlapping()` in `routes/console.php`. It dispatches one `DraftApplicationFollowUpJob` per due application and does no AI work itself, so a slow model can't stall the scheduler. The job copies `RunCompanyIntelJob`'s shape (`tries = 1`, explicit `timeout`, `failOnTimeout`).

The job re-checks "still due" inside a transaction that holds a row lock on the application before inserting. That makes two overlapping runs, or a status change mid-draft, produce at most one draft. If the application stopped being due while the model was writing, the result is dropped.

A failed draft simply isn't stored, so the application is still due on the next daily run. There's no retry bookkeeping. The failure is logged with the application id.

### Drafting: feature default plus built-in prompt id 7

- **System:** `AiSystem::defaultForFeature('application-follow-up')`, required to be an active chat system. There is no fallback to the targeted-resume default: drafting outreach email is a different job, and an unexpected system writing to recruiters is worse than no draft.
- **Prompt:** `AiSystemPrompt::find(7)?->content`, falling back to `ApplicationFollowUpService::buildFollowUpPrompt()`. The service appends fixed output-format instructions (as `CompanyIntelService::instructions()` does), so editing the prompt can't break parsing.
- **Seed:** `seed_application_follow_up_prompt` migration, copied from the company-intel seed.
- **Input:** one user message holding:
  - company, position, location;
  - the job description, truncated to a few thousand characters;
  - the status history (status, date, notes);
  - the contacts as names and roles only, with no email addresses (the model doesn't need them);
  - earlier sent follow-ups (date, subject, body);
  - today's date;
  - the owner's name, from the site config (`Jason Vertucio`, the mail from-name).
- **Output contract:** first line `Subject: …`, a blank line, then the body. Parsing takes the first `Subject:` line. Without one, the subject is `Following up: {position} at {company}` (falling back to `Application::knownPosition()` and `knownCompanyName()`). Empty bodies count as failures.

Prompt content guidance (starter text):

- Short, polite and specific.
- Reference the role and the last known step.
- Never invent facts, dates or names.
- No salary.
- Sign with the owner's name.
- Address the recipients by first name when there is exactly one named contact, otherwise use a neutral greeting.

The other option was to run the draft as a turn in the application's AI session, where the conversation history gives richer context. It was rejected because "I Applied" applications often have no session, a hidden turn would show up in the transcript, and the session's system may not be a chat model fit for outreach.

### Owner notification: one small queued mail per draft

`FollowUpDraftedMail` (`ShouldQueue`, markdown `mail.follow-up-drafted`) goes to `config('resume.follow_ups.notification_email')`. It names the company and position and links to `route('admin.employment.applications.show')`. It doesn't include the contacts or the draft text, so a forwarded or leaked notification exposes nothing about third parties.

A daily digest was considered instead, but the drafts are produced by independent jobs, so there's no natural point to collect them. Volume is a handful per week.

### Sending: approve claims the row, then a queued job sends

`POST …/follow-ups/{followUp}/approve`:

1. In a transaction with `lockForUpdate`, requires status `pending` or `failed`, at least one contact, and a non-empty subject and body.
2. Snapshots the contacts' emails into `recipients`, sets status `sending`, and dispatches `SendApplicationFollowUpJob` after commit.

Because the status moves to `sending` inside the lock, a second approve finds `sending` or `sent` and gets 409. That's the "sent at most once" rule.

`SendApplicationFollowUpJob`:

- `Mail::send(new ApplicationFollowUpMail($followUp))` synchronously inside the job, then marks it `sent` with `sent_at`.
- `tries = 1`. Its `failed()` marks it `failed` with the exception message.
- The admin retries by approving again. There's no automatic retry, so a transient failure can't cause duplicate sends.

`ApplicationFollowUpMail` is not queued itself, because the job is the queue boundary:

- `from` the configured mail address and name;
- `to` the recipients;
- `cc` `mail.cc.address` (`MAIL_CC_ADDRESS`) when set. Production sends from `noreply@jasonvertucio.com`, so the open copy to the owner's real address is what makes a recruiter's reply-all reach a person;
- `bcc` the notification address, unless it equals the cc address (compared lower-cased);
- `text` view rendering the body verbatim, plus an HTML view that escapes the body and converts newlines to `<br>`;
- no `<x-mail::message>` layout.

Recruiters get what looks like a personal email.

### Discarding when the facts change

`ApplicationService::addStatusUpdate()` and turning the switch off (`updateDetails` with `follow_ups_enabled = false`) both call `ApplicationFollowUpService::discardPending($application, reason)` in the same transaction. Editing or deleting a status entry does not discard. Those are corrections, and the admin can discard by hand.

A terminal entry needs no extra code. It goes through `addStatusUpdate` (so the pending draft is discarded), and the terminal status fails the due check from then on.

### HTTP surface (all `auth` + `can:edit-resume`, under the existing applications API prefix)

| Route | Purpose |
| --- | --- |
| `POST contacts`, `PUT contacts/{contact}`, `DELETE contacts/{contact}` | Contacts, via `ApplicationContactController`, `StoreApplicationContactRequest` and `UpdateApplicationContactRequest` |
| `PUT follow-ups/{followUp}`, `POST follow-ups/{followUp}/approve`, `POST follow-ups/{followUp}/discard` | Drafts, via `ApplicationFollowUpController` and `UpdateApplicationFollowUpRequest` |
| `PUT {application}` | Existing endpoint. `follow_ups_enabled` is added to `UpdateApplicationRequest` |

- Each action answers with a new `ApplicationPayload::followUpState()`: contacts, `follow_ups_enabled`, the pending draft, follow-up history, `follow_up_system_configured`, `sent_since_status_change` and `max_per_status`.
- A child reached through the wrong application gets 404, using the existing `assertBelongsTo` pattern, which also covers scoped bindings.
- Domain refusals (not pending, no contacts) are new `ApplicationException` subclasses mapped to 409 by `ApplicationExceptionRenderer`.

### UI

- **Discussion page.** A `FollowUpsCard` on the Details tab, under the status history, with a `useFollowUps` hook mirroring `useStatusUpdates`. It shows:
  - the switch;
  - the contacts list with add, edit and delete (a small dialog);
  - the pending draft as editable subject and body (a multiline `TextField`, not opted in to `useMarkdownPaste`, because the body is plain text and native paste is what's wanted);
  - Approve & send behind a `ConfirmDialog` that lists the recipients;
  - Discard;
  - the history as a compact list (date, status chip, subject, expandable body);
  - an info `Alert` when `follow_up_system_configured` is false;
  - a "N of M sent since the last status change" line, with a note when the maximum is reached or the status is `interviewing` (paused).
- **Applications list.** A small "Follow-up ready" chip next to the status when a pending draft exists, supplied as `has_pending_follow_up` on each row (a `withExists` in `ApplicationController::index`).
- **Styling.** Existing admin tokens and MUI components, no gradients or shadows. Read `.claude/context/admin-design.md` before building.

## Risks / Trade-offs

- **[Real email to real people]** A bug could mail a recruiter. → Only the approve endpoint dispatches the send job, and only from `pending`/`failed` under a row lock. Tests use `Mail::fake()`/`Queue::fake()` and assert that nothing is sent on drafting, on discard, or on a second approve. Local `.env` must not point at Mailjet when testing by hand; use `MAIL_MAILER=log`.
- **[Scheduler not running in production]** If `schedule:run` isn't wired up, nothing is ever drafted, and the failure is silent. → The deploy steps include checking for the cron entry. The existing scheduled sweeps depend on it too, so it's probably present, but it hasn't been verified from this repo.
- **[Stale drafts]** A pending draft can sit for days. → Status changes and the switch discard it. Otherwise the admin sees it on the list and can discard it. No auto-expiry.
- **[AI cost]** At most one draft per application per week, at most `max_per_status` sends per status stage, and only for applications the admin switched on. Discarded drafts don't count toward the cap, so repeatedly discarding means a new draft each week. That is acceptable, because turning the switch off stops it.
- **[Cap stops silently]** Once the cap is reached, nothing more is drafted, and an admin might think follow-ups are broken. → The Discussion card shows "N of M sent since the last status change" and says when the cap is reached.
- **[Sending reputation]** Mail from noreply@jasonvertucio.com via Mailjet depends on the domain's SPF/DKIM already being set up for Mailjet. → Same transport the site already uses for comment notifications. The developer should confirm noreply@jasonvertucio.com is an authorised Mailjet sender.
- **[Third-party data]** Contacts are PII. → `edit-resume` only, not in MCP tools, not in owner notifications. They're deleted with the application's rows only on hard delete, since soft delete keeps them, as it keeps everything else.

## Migration Plan

1. Migrations: `create_application_contacts_table`, `create_application_follow_ups_table`, `add_follow_ups_enabled_to_applications_table`, `seed_application_follow_up_prompt`. Run them on the app database and on `wink` (`DB_DATABASE=wink php artisan migrate`). All have real `down()` methods. The seed's `down()` deletes only the row it created.
2. Deploy (developer-owned):
   - `composer install`, `npm ci && npm run build`, `php artisan migrate`, `php artisan optimize:clear`;
   - restart the `queue:work` supervisor program;
   - confirm the cron entry for `php artisan schedule:run`.
3. After deploy, mark one chat AI system as the `application-follow-up` default. Until that's done, nothing is drafted.
4. Rollback: turn off all switches (or leave them, since nothing sends without approval), revert the code, and roll back the four migrations.

## Open Questions

- Exact daily run time (09:00 server time assumed). It can change without touching the spec.
