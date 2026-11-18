## Context

- **Tool discovery.** `AppServiceProvider` registers `app/Services/Mcp/Tools` with
  `CodeTalkerServiceProvider::addToolDirectory()`. `ChatBotToolRegistry` walks that
  tree recursively, creates every concrete `Laravel\Mcp\Server\Tool` subclass with a
  `context` (`ToolContext`) override, and filters the result by
  `AiSystem::allowed_tools`. A class in `app/Services/Mcp/Tools/ChatBot/` therefore
  becomes a grantable internal tool and shows in the admin picker with no other
  wiring. The precedents are `GetRecentBlogPostsTool` and `GetSiteInfoTool`.
- **The resume-edit gate.** `AuthorizedResumeEditTool` adds a second gate on
  `edit-resume` through `shouldRegister()`, and checks it again at call time. It
  checks with `$user->can('edit-resume')`.
- **Turns run in the queue worker.** A chat turn runs inside
  `RunConversationTurnJob`. Production runs it under `queue:work --timeout=299`, and
  the turn as a whole has a ceiling of `code-talker.conversations.max_stream_seconds`
  (300 s), which also covers the model's own generation. A blocking tool call in the
  worker does not starve the browser, because `TurnEventStream` sends its own
  heartbeats while it polls the store. The tool still has to finish well inside both
  limits.
- **Keystone gates in the console.** Keystone registers its gates only when the app
  is not running in the console, unless the environment is `testing` (see memory
  *Keystone gates are off in console*). `queue:work` runs in the console, so
  `$user->can()` returns false there in production, while tests pass.
- **Live API, probed 2026-11-18.** The search returns `{jobs: [...], pageMetadata:
  {size, number, totalElements, totalPages}}`. `page` is 1-based. Raw `jobType` values
  seen: `Perm`, `Contract`, `Contract To Perm`, `Contract, perm possible`. `payRate.max`
  can be missing. The details endpoint returns only `{description, disclaimer}`.
  `description` is HTML: sections are separated by `<br><br>`, single newlines
  separate lines, and entities such as `&amp;` are escaped. The headings seen are
  "Required Skills & Experience", "Nice to Have Skills & Experience" and "Job
  Description". An unknown id returns 404. `https://insightglobal.com/jobs/{id}`
  answers 200, but it is a client-rendered page, so the job URL format comes from the
  source prompt and has not been verified against page content.
- **Keyword probe, 2026-11-18.** The keyword search is loose full-text matching.
  `frontend-developer`, `ui-developer` and `"frontend developer"` each returned the
  same 281 jobs in the same order, mostly unrelated developer roles. `developer`
  returned 2,625. Single words are narrower but still noisy: `vue` returned 19,
  `react` 121, and `frontend` 107, with C++ roles mixed in. That is why the fallback
  list is single words, and why the tool description steers the persona toward
  them.
- **Location probe, 2026-11-18.**
  - `filter.locations` accepts a city name (`philadelphia`) or a ZIP (`19103`), with
    `filter.distance` widening either one. `usa`, `united states`, `us`, `pa`,
    `pennsylvania`, `philadelphia, pa` and coordinates all return 0 jobs without
    an error.
  - Search entries carry only `workAddress.locality` and `administrativeArea`. There
    is no country field.
  - Remote jobs have a listed city too, and the location filter matches on it.
    Remote-only for "developer" gave 492 jobs; adding Philadelphia within 25 miles
    left 13.
  - Of the first 50 remote-only results, 10 were international: São Paulo, Chihuahua,
    Bengaluru. Two of those were listed at US addresses but titled "INTL": "Atlanta,
    GA · Sr. Frontend Software Engineer - INTL INDIA" and "Bernards, NJ · INTL LATAM
    - …".
- **Filter probe, 2026-11-18.**
  - `filter.jobType=Contract%20To%20Perm` returns HTTP 500.
  - `filter.jobType=ContractToPerm` returns only "Contract To Perm" jobs.
  - `filter.jobType=Contract` is a superset: it also returns "Contract To Perm" and
    "Contract, perm possible" jobs.
  - `filter.locations=all` returns zero jobs, while leaving it out returns
    everything.
  - `filter.locations=philadelphia` matches the city exactly, and adding
    `filter.distance=10` widens it to nearby cities.

## Goals / Non-Goals

**Goals:**

- Behaviour is deterministic and covered by tests that never touch the network.
- The HTTP, paging and normalization code lives in plain services the tool calls,
  so a future job-board tool can reuse the shape.

**Non-Goals:**

- Creating `Application` records from results, or linking results to Company Intel.
  The persona or the user can do that afterwards with existing tools.
- Scraping the HTML job page. The JSON endpoints are the only source.
- Exposing the tool on the public MCP server (`/mcp`).
- Changing `AuthorizedResumeEditTool`. Its `can()` check may have the console
  problem described above. That is flagged for the developer and not fixed here.
- Extracting skills or technologies with an LLM. The tool returns section text, and
  the persona reads it.
- Having the persona translate unrecognized job types. The tool reports them and
  asks for a code update. It does not hand interpretation to the model.

## Decisions

### 1. Deterministic PHP tool, not a prompt that drives `http-request`

The tool sends every request itself, with Laravel's `Http` client, to a fixed host.
Spacing, paging, the date window and deduplication cannot be skipped by a weaker
model. A fixed host and fixed paths also mean there is no SSRF surface, which is why
the tool does not go through the package's `WebFetcher` and `RequestPolicy`. Those
exist to police model-chosen URLs. The tool does not consult the AI system's
`web_tool_policy` domain list either. An administrator who grants this tool has
approved its one host, and a domain list kept for `fetch-web-page` should not
silently disable it. Redirects are turned off (`withoutRedirecting()`), and a 3xx
counts as a failure.

*Alternative considered:* keep the prompt and ship it as a persona. That was
rejected because it is the current state the proposal sets out to replace.

### 2. Files

- `app/Services/Mcp/Tools/ChatBot/SearchInsightGlobalJobsTool.php` is the tool
  (`#[Name('search-insight-global-jobs')]`). It handles the schema, the gate, input
  parsing and the response.
- `app/Services/JobSearch/InsightGlobal/` holds the services. It sits outside the
  discovered tree, so none of them can become a phantom tool.
  - `InsightGlobalClient` sends `search(array $query)` and `details(string $id)`
    requests. It sets the `Accept: application/json` header, the User-Agent from
    `WebScraperUserAgent::forBotName($botName, 'job-search')` and the timeouts. It
    spaces requests and enforces the budget through a `RequestBudget`.
  - `RequestBudget` holds the request count and elapsed-time limits and the 2 s
    spacing.
  - `InsightGlobalSearch` builds the query, runs the keyword loop and paging, applies
    the date window, removes duplicates, caps the results and then fetches details.
  - `InsightGlobalJobNormalizer` turns a search entry and a details body into the
    output shape. It splits the description into sections and formats the salary.
  - `InsightGlobalSearchCriteria` holds the validated inputs and builds each query.
    `ClientResult` and `SearchOutcome` are small value objects.
- `app/Enums/InsightGlobalJobType.php` is the shared job-type constant from §4.

### 3. Gate with `hasPermissionTo('edit-resume')`

The tool takes `ToolContext` in its constructor. `shouldRegister()` and the
call-time guard both resolve `User::find($context->userId)`, then call
`hasPermissionTo('edit-resume')`. That gives the same answer in the queue worker as
in a test, because it does not depend on Keystone registering gates. The class
copies the gate shape of `AuthorizedResumeEditTool` but not its `can()` call, and it
does not extend it, because it is not a resume-edit tool.

### 4. Query construction

The client builds the query string by hand from a list of `[name, value]` pairs,
each passed through `rawurlencode`, and appends it to the URL. Laravel's `Http`
array serializer would turn repeated keys into `filter.jobType[0]=…`, and PHP's
`http_build_query` does the same. The API expects bare repeated keys such as
`sort=a&sort=b`.

- The parameter order is `keyword`, `page`, `size`, `sort`…, `filter.jobType`…,
  `filter.includeOnlyRemoteWork`, `filter.minimumPayRateGreaterThan`,
  `filter.locations`, `filter.distance`, `filter.status`.
- Pairs with a null value are dropped before encoding.
- Job-type inputs map to filter values through one constant:
  `Contract → Contract`, `Perm → Perm`, `Contract To Perm → ContractToPerm`. The
  normalizer's known raw types come from the same constant, so the input enum, the
  filter values and the recognized raw values cannot drift apart.
- `filter.locations` is sent only for a caller-given location, lowercased. It is
  never sent as `all`, which returns no jobs (see *Context*).
- Keyword normalization lowercases, trims and collapses whitespace to `-`.
- Distance rounds to the nearest multiple of 5, with a minimum of 5. The default is
  10. It is sent only when a location is given and the search is not remote-only.

### 5. Order of work: all searches first, then details for what will be returned

1. For each keyword, fetch page 1, then later pages while `number < totalPages`.
   When the sort is exactly `[postedDate,desc]`, stop as soon as a page's oldest
   job is before the window.
2. Filter every entry to the window. Merge on `requisitionId` and keep the keywords
   that matched each one.
3. Sort newest first and cap at `max_results`.
4. Fetch details only for the capped set.

This way the budget is spent on jobs that will actually be returned. A job's
`matched_keywords` lists every keyword whose results contained it.

### 5a. US-only filter

The location filter can't express "USA", and results have no country, so the tool
filters on its own side. Doing so costs nothing, because it uses fields already in
each search entry.

- `InsightGlobalJobNormalizer::isUsJob(array $entry)` holds a constant list of the 50
  state codes plus `DC` and `PR`. A job passes when its trimmed, uppercased
  `administrativeArea` is in that list and its title does not match `/\bINTL\b/i`.
- A missing state fails. The rule is "shown to be in the US", not "not shown to be
  abroad".
- In the run loop, a job that fails is skipped right after the window check and its
  id goes into an exclusion set. `excluded_non_us` is that set's size. Because this
  happens before the merge, a job listed abroad never reaches details.
- The window-edge early stop still uses the oldest job on the page, excluded ones
  included. Exclusion doesn't change what later pages hold.
- `us_only` defaults to true. The persona prompt is meant to search remote work
  across the US, and without the filter about 1 in 5 remote results is
  international.

*Alternative considered:* rejecting `location: "USA"` and other non-city values with
a correctable error. That is useful, but separate from this filter. It is not
included here.

### 6. Budget numbers

- At most **45 requests** and **120 s** elapsed per call.
- Before each request after the first, the budget sleeps 2 s with
  `Illuminate\Support\Sleep`.
- Before sleeping, it checks that the elapsed time plus 2 s plus the request
  timeout still fits in 120 s. When it does not, it refuses the request, and
  `InsightGlobalSearch` records what was skipped.
- Per-request timeouts are a 10 s connect and a 15 s total.

Elapsed time is measured with `Carbon::now()`. Tests can then use
`Sleep::fake(syncWithCarbon: true)`, so faked sleeps advance the clock and the time
budget can be tested without real waiting. In the worst default case, three fallback
keywords at two pages each plus 25 details make 31 requests, about 62 s of spacing
plus latency. That fits inside the budget, and the budget fits inside the 300 s turn
and the 299 s job.

*Alternative considered:* fetch details in a queued job and return later. That was
rejected because a chat tool call has to return its result within the turn.

### 7. Date window

`today` is parsed as `Y-m-d` in the app timezone (UTC). The default is
`Carbon::today()`. `days_back` is an integer from 0 to 60, with a default of 7. The
window runs from `today->copy()->subDays(days_back)->startOfDay()` to
`today->endOfDay()`. `postedDate` is parsed as UTC, and an unparseable date excludes
the job. The tool's description tells the persona to pass the user's local date from
`get-temporal-information` when that matters. The response carries `search_window:
{today, days_back, today_source, days_back_source, from, to}`, where each source is
`supplied` or `default`.

### 8. Output shape

`Response::structured()` with:

```
{
  search_window: {...},
  keywords_searched: ["vue", ...],
  filters: { job_types, remote_only, minimum_pay_rate, location, distance, sort, status },
  total_found: int, returned: int, capped: bool,
  complete: bool, skipped: [ "react page 2", "details 8de6…" ],
  errors: [ { keyword|requisition_id, status|reason } ],
  message?: "No matching jobs were found.",
  excluded_non_us: int,
  unrecognized_job_types?: [ { job_type, count, job_ids: [...] } ],
  notice?: "The tool met job types it does not recognize: … Tell the user and ask for
            the search-insight-global-jobs tool to be updated to handle them.",
  jobs: [ {
    job_id, title, company: "Insight Global", location, remote_status, remote: bool,
    employment_type?, job_type, posted_date (n/j/Y), posted_at (ISO 8601),
    salary?, pay_rate?: {min, max?, currency, type},
    job_url, matched_keywords: [...],
    required_skills?, preferred_skills?, job_description?, details_error?
  } ]
}
```

- Keys whose value is null are omitted from each job.
- Employment type comes from an exact lookup on the trimmed, lowercased raw type:
  `perm` → Full Time, `contract` → Contract, `contract to perm` → Contract to Hire.
  There is no prefix or fuzzy matching. A miss leaves `employment_type` off the job,
  and the raw value is added to `unrecognized_job_types`.
- `unrecognized_job_types` is built from the returned jobs only, after the window
  and the cap. It is in first-seen order. Both `unrecognized_job_types` and `notice`
  are absent when the list would be empty.
- When every search fails, `Response::error()` names each failure.
- When the searches succeed but find nothing, the response is a structured result
  with `jobs: []` and the message.
- The description is turned into plain text in this order: `<br>` becomes `\n`,
  `strip_tags`, then `html_entity_decode` with `ENT_QUOTES | ENT_HTML5`, then blank
  runs are trimmed.
- The text is split on exact heading lines, compared case-insensitively.
- Salary formatting: `Hourly` → `/hr`, `Annually` → `/yr`, any other type → ` (type)`.
  Whole numbers print without decimals and other values with two
  (`number_format`). Only `USD` gets a `$` prefix. Any other currency is written as
  a code suffix.

The tool description tells the persona to pass single-word technology keywords
(such as `vue` or `typescript`), not multi-word or generic terms like "developer",
for the reason in *Context*. It names the fallback list.

The tool description adapts the source prompt's presentation rules for the persona.
It tells the persona to state the date and days back used, to present one section
per job, to give CSV or JSON in a fenced block labelled with its format, and to
never add a field the result does not contain. It also tells the persona that when
a `notice` is present, it must pass the notice on to the user. It must not
translate the unrecognized job types itself.

### 9. Inputs (schema)

| Input | Type and limits | Default |
| --- | --- | --- |
| `keywords` | string array, up to 10 | the fallback list |
| `today` | string, `Y-m-d` | server date |
| `days_back` | integer, 0–60 | 7 |
| `job_types` | string array, each `Contract`, `Perm` or `Contract To Perm` | none |
| `remote_only` | boolean | false |
| `us_only` | boolean | true |
| `minimum_pay_rate` | number | per the spec |
| `location` | string | none (parameter left out) |
| `distance` | integer, in miles | 10 |
| `sort` | string array, from the four known values | `postedDate,desc` |
| `include_details` | boolean | true |
| `max_results` | integer, 1–50 | 25 |

- Invalid input (a bad date, a value out of range, an unknown enum) returns
  `Response::error` with a correctable message. It is never silently clamped, except
  that `distance` rounds to a multiple of 5.
- `job_types` is limited to the three values whose filter spelling was verified
  (see *Context*).
- Flat arrays of strings are used rather than nested objects. `HttpRequestTool`
  notes that small models are unreliable with nested objects.

## Risks / Trade-offs

- [Insight Global changes or blocks the unofficial JSON endpoint] → Each failure is
  reported per keyword with its status. The tool never makes up results, and the
  tests pin the parsing to the probed shape. The fix is a code change to one client.
- [A long tool call ties up the single production queue worker for up to about
  120 s] → The tool is gated to `edit-resume` users and to systems an administrator
  grants it to, and the budget caps it.
- [Detail text is large: 25 jobs × about 1.5 KB] → `max_results` defaults to 25,
  the disclaimer is dropped, and `include_details: false` gives a light listing.
- [The `today` default is a UTC date, which can be a day off from the user's local
  date late in the evening] → The response says the date was defaulted, and the
  description tells the persona to pass the user's local date.
- ["On-site or hybrid" is less specific than the prompt's Remote/Hybrid/On-site
  labels] → The search data has only a boolean. Saying more would be inventing it.
  The persona may refine the label only when the description text states it.
- [Insight Global keeps inventing loose job-type values] → Each one is reported, with
  a request for an update, on every run until a mapping is added to the one constant
  in §4. Jobs with those values are still returned, with their raw type.
- [Some Indian state codes are also US codes: TN, GA, AR, MN] → An Indian job listed
  as, say, "Chennai, TN" without "INTL" in its title would pass as Tennessee. The
  "INTL" title check catches the cases seen in the probe. `us_only: false` is the
  escape hatch when the filter drops something it shouldn't.
- [A `Contract` filter also returns contract-to-perm and "perm possible" jobs] →
  That is the API's behaviour. The results show each job's raw type, so nothing is
  hidden.
- [Until CodeTalker's `ChatBotToolRegistry` honors `shouldRegister()`, the chat loop
  still offers the tool to a user without `edit-resume` on a system that grants it]
  → The call-time guard refuses that user before any request is sent. The
  developer's CodeTalker fix makes the tool hidden there too. Registry-level tests
  for that are follow-up work, outside this change.
- [`AuthorizedResumeEditTool` and `AuthorizedResumeTool` may refuse in the
  production worker because of `can()`] → Out of scope. It is raised with the
  developer, and this tool avoids the problem.

## Migration Plan

No migrations, config or assets are involved. Deploy as usual, then restart the queue
worker (`sudo supervisorctl restart <program>`) so `queue:work` loads the new class.
An administrator then grants `search-insight-global-jobs` on the AI system that
should use it. To roll back, revert the commit and restart the worker. A stale grant
for the name shows as "no longer available" in the picker and causes no error.
