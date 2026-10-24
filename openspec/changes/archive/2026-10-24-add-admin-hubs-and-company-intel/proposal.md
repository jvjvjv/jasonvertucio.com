## Why

The Admin Dashboard is a flat grid of ten tiles, four of which (Applications, Application Metrics, Resume Management, Cover Letter Management) are one job-search workflow, and two of which (Blog Admin, Comments) are one blogging workflow. Grouping them into hubs makes the dashboard about areas of work rather than individual pages. The job-search area also lacks research: once an application is sent, the next step is learning about the company and the role, and today that happens outside the site with nothing kept against the application.

## What Changes

- **Employment hub** at `/admin/employment`, the first tile on the Admin Dashboard and the first area after the dashboard in the app bar. It holds, in order: Applications, Application Metrics, Resume Management, Cover Letter Management, and the new Company Intel. Those four tiles leave the dashboard. Existing page URLs do not change; their back links point at the hub.
- **Blog hub** at `/admin/blog`, the last tile on the Admin Dashboard, holding Blog Admin (`/canvas`) and Comments. Both tiles leave the dashboard. The Blog Admin tile's permission check is corrected from the non-existent `manage-blogs` to `manage-blog`, and it is marked external since `/canvas` is not an Inertia page.
- **Company Intel**, a new feature split into two kinds of record:
  - a **Company** (name, website, headquarters, employee count, revenue, industry and similar metadata) with **Company insights** (culture, values, products, news, leadership and similar findings, each with an optional source), shared by every application at that company;
  - **Role intel**, one per application, holding what was learned about the role and pointing at the Company it is for.
  - An AI session (feature `company-intel`) gathers both through dedicated tools and stays open for follow-up chat.
  - A seeded, editable **Company Intel system prompt**, added to the fixed-id set beside Default, Creative, Technical, Targeted Resume and Cover Letter, and a new **`company-intel` feature default** on AI systems.
  - Intel starts **automatically, as a queued job**, when an application is marked applied and a `company-intel` default system exists. Without a default nothing starts automatically; starting by hand asks the admin to choose among the AI systems that use the Company Intel prompt.
  - A **Company Intel list page** in the hub listing every intel session with its application, company and state. Sessions cannot be started there.
  - A **Wrong company** action: detaches the company from the role intel, takes a hint for the right one, and has the agent research again in the same session (or attaches an existing company directly). The rejected company is deleted when nothing else refers to it, and the agent may not attach it to that role again.
- **Applications changes**:
  - "I Applied" with an AI system selected also starts a session whose first message asks only for a fit score ("I have already applied to this. Please provide a fit score, but there is no need for a targeted resume or cover letter."). With no system selected it behaves as today. "Begin Analysis" on an application already in the pipeline uses the same message.
  - The list gains an **Intel** column linking to the application's intel session, or to another application's session at the same company when only the company is known.
  - Rows without role intel get a **Begin intel** action. The actions column is ordered so the always-present icons sit on the far right: Pass, Begin intel, Open, Delete.
  - The Discussion page header gains an **Intel** item: it opens the intel session when one exists; otherwise it starts one and opens it with the first turn streaming live.

## Capabilities

### New Capabilities
- `admin-hubs`: the Admin Dashboard's grouping into the Employment and Blog hubs, which tiles each holds and in what order, and their access rules.
- `company-intel`: Company, Company insight and Role intel records; the intel AI session, its tools, system prompt and feature default; automatic and manual starting; the Company Intel list and session pages; correcting a wrong company.

### Modified Capabilities
- `application-tracking`: "I Applied" may start a fit-score-only session; Begin Analysis on an applied application uses the fit-score-only message; the list gains the Intel column, the Begin intel action and a fixed action order; the Discussion page gains the Intel header item; marking an application applied starts intel when a default exists.

## Impact

- **Navigation**: `resources/js/admin/navigation.json`, `AdminController` (two new hub actions), new hub pages under `resources/js/admin/pages/`, back links on the moved pages.
- **Data**: new tables `companies`, `company_insights`, `role_intels`; a data migration seeding the Company Intel system prompt at fixed id 6, alongside the existing seeded prompts (ids 1–5). Ids 1–49 become reserved for built-in prompts: admin-made prompts in that range are moved to 50+ (their AI system assignments follow) and new ones start at 50. Migrations must be run against both `jasonvertucio` and `wink`.
- **Backend**: new `CompanyIntelService`, `RunCompanyIntelJob` (the app's first queued job besides mail), a `CompanyIntelToolRegistry` and tools under `app/Services/Mcp/Tools/CompanyIntel/`, new controllers and routes under `/admin/employment/company-intel` and `/api/admin/employment/company-intel`; changes to `ApplicationService`, `TargetedResumeService`, `ApplicationController`, the AI system form requests (`company-intel` feature key).
- **Frontend**: Applications list and Discussion page, New Session page, BeginAnalysisPanel, AI system form feature list, new Company Intel pages.
- **Operations**: production's `queue:work` worker must be restarted on deploy (already required); intel runs must finish inside the worker's 299 s timeout.
- **Docs**: CLAUDE.md gains Employment hub, Blog hub and Company Intel sections.
- No changes to `jvjvjv/code-talker`.
