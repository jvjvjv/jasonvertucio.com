## Why

After I apply, the people I'm talking to about the job (the recruiter who reached out, a hiring manager) live only in my inbox, and following up when a process goes quiet depends on me remembering to do it. If an application keeps the addresses of its contacts, the system can draft a polite check-in once a week has passed with no news, and I only have to read it and approve it.

## What Changes

- **Contacts on an application.** An application can hold any number of contacts. Each has an email address (required, unique within the application) and an optional name, role (e.g. "Recruiter") and notes. Contacts are added, edited and removed on the Discussion page.
- **A follow-up switch per application**, off by default. When it's on, every contact on that application is a recipient.
- **Weekly AI-drafted follow-ups, held for approval.** A daily scheduled run finds applications that are due for a follow-up and asks an AI system to draft one for each:
  - Due means:
    - follow-ups are on;
    - the status is `applied`, `interviewed` or `offered`, which pauses follow-ups while `interviewing`;
    - there is at least one contact and no draft is already waiting;
    - fewer than the maximum (a site-wide setting, default 3) have been sent since the latest status change;
    - at least 7 days have passed since the later of the latest status-history entry and the last follow-up sent.
  - The maximum counts per status stage. A new status entry starts the count again.
  - The draft is written from the job, its status history and earlier follow-ups. It is stored as **pending** and is never sent on its own.
- **I'm told when a draft is ready.** Each new draft sends me a short notification email linking to the application. The Applications list marks applications with a draft waiting.
- **Approve, edit or discard.** On the Discussion page I can edit a pending draft's subject and body, then **Approve & send** it to the current contacts, or **Discard** it. A sent follow-up records its recipients and send time and appears in the application's follow-up history. A failed send is marked failed, with the reason, and can be retried.
- **Follow-ups stop on their own.** They stop when I turn the switch off, when the application reaches a terminal status (`accepted`, `hired`, `rejected`, `withdrawn`), or when the maximum for the current stage has been sent. They pause while the status is `interviewing`. Either event, and any new status-history entry, discards a pending draft, because it was written against stale facts.
- **A new AI feature default, `application-follow-up`**, with a built-in, editable "Application Follow-up" system prompt (id 7). Drafting uses the system set as that default. If none is set, nothing is drafted and the Discussion page says why.
- Follow-up emails go out as plain personal mail from the site's mail address (`MAIL_FROM_ADDRESS`, noreply@jasonvertucio.com, via the existing Mailjet transport). `MAIL_CC_ADDRESS` (me@jasonvertucio.com) is openly copied so replies reach me, and the notification address is BCC'd unless it is that same address.

## Capabilities

### New Capabilities

- `application-follow-ups`: application contacts, the per-application follow-up switch, when a follow-up is due, AI drafting, the owner notification, approving, editing, sending and discarding, and when follow-ups stop.

### Modified Capabilities

- `company-intel`: the "Company intel feature default" requirement's list of accepted feature defaults gains `application-follow-up`.
- `ai-system-voice-roles`: the "Voice feature defaults match the system's role" requirement's list of chat features gains `application-follow-up`.

## Impact

- **Database:**
  - new `application_contacts` and `application_follow_ups` tables;
  - a `follow_ups_enabled` boolean on `applications`;
  - a migration seeding built-in prompt id 7, which refuses to overwrite an existing id.
  - Migrations must be run against both the app database and `wink`.
- **Backend:**
  - new models `ApplicationContact` and `ApplicationFollowUp`, with factories;
  - an `ApplicationFollowUpService`, a scheduled command, and queued jobs for drafting and sending;
  - two mailables: the follow-up itself and the owner notification;
  - new nested routes and controllers under `/api/admin/employment/applications/{application}/` (contacts, follow-ups), with FormRequests;
  - `UpdateApplicationRequest` gains `follow_ups_enabled`;
  - `ApplicationService::addStatusUpdate()` discards pending drafts;
  - `ValidatesAiSystemRole::FEATURE_ROLES` gains the new feature key;
  - `config/resume.php` gains a `follow_ups` section (cadence days, maximum per status stage, owner notification address).
- **Frontend:**
  - a Contacts & Follow-ups card on the Discussion page's Details tab;
  - a "follow-up ready" marker in the Applications list;
  - `CHAT_FEATURES` and the prompt lock in `resources/js/admin/pages/ai/systems/Form.tsx` gain the new feature.
- **Operations** (developer-owned): production must run Laravel's scheduler (`schedule:run` every minute), and the `queue:work` worker must be restarted on deploy. Recruiters will receive real email from noreply@jasonvertucio.com, copied to `MAIL_CC_ADDRESS`, once a draft is approved. Set both in production `.env`.
- **Privacy:** contacts are third parties' personal data. They are visible only behind `edit-resume`, and are not exposed through the public MCP server or the targeted-resume session's tools.
