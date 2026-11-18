## Why

Searching Insight Global for new postings currently depends on a long system prompt
that walks a persona through building URLs, calling `http-request`, paging, waiting
between calls and fetching each job's details. Every step is left to the model,
so results vary from run to run, a small model can skip pages or details, and the
prompt has to restate the API's quirks. The search is mechanical. It belongs in
one deterministic tool that returns the same normalized results every time, and
the model's job becomes presenting them.

## What Changes

- Add a chat-bot tool named `search-insight-global-jobs`. Like any internal tool, it
  is granted per AI system through `allowed_tools`.
- The tool calls Insight Global's JSON search endpoint
  (`https://insightglobal.com/all/jobs`) with only the parameters that have values,
  URL-encoded. It pages automatically, using the response's `pageMetadata`, and
  removes duplicate jobs by `requisitionId`.
- With no keyword it runs the fallback list in order: `vue`, `react`, `typescript`.
  Keywords are sent lowercase and hyphen-separated (`twilio-developer`).
- That list is all single words on purpose. The source prompt's fallback list was
  `vue`, `react`, `frontend developer`, `front end developer`, `ui developer`. A live
  probe showed `frontend-developer`, `ui-developer` and `"frontend developer"` each
  return the same 281 any-developer jobs (C++, Salesforce, Power BI), which buried
  the few relevant ones.
- It keeps only jobs posted within a date window: `today` (default: the server's
  current date) back through `days_back` days (default 7). It always reports the
  date and window it used, so the model can state them.
- It drops international jobs by default (`us_only`, on unless turned off). The
  location filter accepts only a city or ZIP, so it cannot express "USA", and search
  results have no country field. In a live probe, 1 in 5 remote results was outside
  the US, some of them listed at US addresses ("Atlanta, GA · … INTL INDIA"). A job is
  kept only when its listed state is a US state or DC, and its title has no "INTL".
  The response reports how many jobs were dropped.
- It applies the prompt's filter defaults. Status is `active`. Sort is
  `postedDate,desc`. When the only job type is Contract, the minimum pay rate is 70;
  when the only job type is Perm, it is 130000. Distance is sent only for a
  non-remote search with a specific location.
- The job-type filter accepts `Contract`, `Perm` and `Contract To Perm`. The last
  is sent as `ContractToPerm`, because a live probe showed `Contract To Perm`
  returns HTTP 500.
- `filter.locations` is left out when no location is given. The source prompt's
  table gives `all` as its default, but the live API returns zero jobs for
  `filter.locations=all`.
- For each job in the window it fetches the details endpoint
  (`/all/jobs/{requisitionId}/details`) and splits the description into required
  skills, preferred skills and job description when those headings are present.
  Otherwise it returns the description whole. The boilerplate disclaimer is dropped.
- It normalizes each job. Location becomes `City, ST`. Remote status is Remote or
  "On-site or hybrid". Employment type is mapped from the three known raw job
  types, and the raw value is also kept. Salary is formatted from `payRate`. The
  posted date is returned both as a readable date and as ISO. The job URL is built
  from the requisition id. Fields the API does not supply are omitted, never guessed.
- Insight Global returns loose job-type values such as "Contract, perm possible".
  The tool never maps a value it does not recognize. It lists each unrecognized
  value, and adds a notice telling the persona to tell the user and ask for the tool
  to be updated. Having the persona translate those values itself is out of scope.
- It waits at least 2 seconds between consecutive requests. It works within a fixed
  request count and wall-clock budget so it finishes well inside the queue worker's
  `--timeout=299`, and it reports when it stopped early.
- It reports failures plainly. A failed search returns the HTTP error. A failed
  detail fetch keeps the job and marks the error. No results returns "No matching
  jobs were found."
- It stores nothing: no database rows, no cache and no memory.
- It is gated a second time on the conversation user's `edit-resume` permission, the
  same gate as the employment area. It does not register for anyone else.

## Capabilities

### New Capabilities

- `insight-global-job-search`: the `search-insight-global-jobs` chat-bot tool. Covers
  its inputs and defaults, query construction, pagination and deduplication, the
  date window, detail fetching, normalized output, reporting of unrecognized job
  types, request spacing and budget,
  error reporting, access gating, and the rule that it persists nothing.

### Modified Capabilities

(none)

## Impact

- **New code**:
  - The tool class under `app/Services/Mcp/Tools/ChatBot/`, discovered through the
    host tool directory already registered in `AppServiceProvider`.
  - An Insight Global client and normalizer outside the discovered tools tree, in
    `app/Services/JobSearch/`.
- **Outbound HTTP**: requests to `insightglobal.com` only, made by the queue worker
  during a chat turn. They are rate-spaced and budget-capped.
- **No** migrations, routes, UI, config keys or new dependencies. The admin tool
  picker lists the tool automatically under internal tools.
- **Docs**: a short entry in `.claude/context/applications.md`.
- **Tests**: a feature test using `Http::fake()`, `Sleep::fake()` and a fixed
  `Carbon` now. No live network.
