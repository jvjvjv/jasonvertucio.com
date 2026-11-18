## 1. Setup

- [x] 1.1 Create `feature/add-search-insight-global-jobs-tool` from `develop`. Verify with `git branch --show-current`.
- [x] 1.2 Save trimmed fixtures from the probed shapes (design.md, *Context*) under `tests/Fixtures/insight-global/`:
  - a search page 1 of 2;
  - a search page 2 of 2;
  - an empty search page;
  - a details body with all three headings;
  - a details body with no headings.

  The fixtures need a `Perm` annual range, a `Contract` hourly minimum-only rate, a `Contract To Perm` job, a `Contract, perm possible` job (the unrecognized type), a job with no `payRate`, and a remote job. Verify each file parses with `php -r 'json_decode(file_get_contents($argv[1]), flags: JSON_THROW_ON_ERROR);' <file>`.

## 2. Normalizer

- [x] 2.1 Create `App\Services\JobSearch\InsightGlobal\InsightGlobalJobNormalizer` with `php artisan make:class`, as design.md §8 describes:
  - location `City, ST`;
  - remote status Remote or "On-site or hybrid";
  - the employment type mapping: an exact lookup on the trimmed, lowercased raw type, covering `Perm`, `Contract` and `Contract To Perm` only, from the shared job-type constant (design.md §4). The raw `job_type` is kept, and an unknown type gets no `employment_type`. Expose whether a job's type was recognized, for 4.3;
  - `posted_date` (`n/j/Y`) and `posted_at` (ISO);
  - salary formatting plus the raw `pay_rate`;
  - `job_url`;
  - company "Insight Global";
  - null keys dropped.
- [x] 2.2 Add the description conversion and section split: `<br>` becomes a newline, tags are stripped, entities are decoded, and the text is split on the three headings, falling back to the whole text as `job_description`. The disclaimer is never returned.
- [x] 2.3 Write `tests/Unit/Services/JobSearch/InsightGlobalJobNormalizerTest.php` covering:
  - every scenario in the "Results are normalized and never invented" requirement;
  - the two description scenarios (known headings, and no headings);
  - "Contract, perm possible" has no employment type and is flagged as unrecognized;
  - `  perm ` is recognized despite case and whitespace. Verify with `php artisan test --compact tests/Unit/Services/JobSearch/InsightGlobalJobNormalizerTest.php`.

## 3. Client and budget

- [x] 3.1 Create `RequestBudget` (design.md §6): 45 requests, a 120 s budget measured with `Carbon::now()`, and 2 s spacing via `Sleep`. Before each request it reports whether that request is allowed.
- [x] 3.2 Create `InsightGlobalClient`:
  - `search(list<array{string, string|null}> $pairs)` and `details(string $requisitionId)`;
  - the query string built by hand from repeated, `rawurlencode`d keys, with null pairs dropped (design.md §4);
  - the headers `Accept: application/json` and the `WebScraperUserAgent::forBotName(..., 'job-search')` User-Agent;
  - `withoutRedirecting()`, with a 10 s connect timeout and a 15 s total timeout;
  - every call goes through `RequestBudget`;
  - it returns a result that either holds decoded JSON or holds a failure with a status or reason. A body that is not JSON, or has no `jobs` array, is a failure saying the response could not be parsed.
- [x] 3.3 Write a unit test for `RequestBudget` using `Sleep::fake(syncWithCarbon: true)`. It must show three requests sleep twice for 2 s each, that request 46 is refused, and that a request is refused once the elapsed time would pass 120 s. Verify the test passes.

## 4. Search orchestration

- [x] 4.1 Create `InsightGlobalSearch`:
  - build the query pairs from the inputs:
    - keyword encoding, the default sort and `filter.status=active`;
    - repeated `sort`, and repeated `filter.jobType` mapped through the shared constant (`Contract To Perm` is sent as `ContractToPerm`);
    - remote-only;
    - the pay-floor defaults: 70 for Contract only, 130000 for Perm only, none otherwise (including Contract To Perm only), and never without a job type;
    - `filter.locations` only when a location is given, never `all`;
    - distance only for a non-remote search with a location, rounded to a multiple of 5, with 10 as the default;
  - run the fallback keyword list when no keywords are given.
- [x] 4.2 Add the run loop (design.md §5):
  - page each keyword using `pageMetadata`, stopping on an empty page;
  - stop early at the window edge only when the sort is exactly `postedDate,desc`;
  - filter to the date window, merge on `requisitionId` with `matched_keywords`, sort newest first, and cap at `max_results` with `capped` and `total_found`;
  - fetch details for the capped set only, when details are on;
  - record failures per keyword and per detail, and record skipped work when the budget refuses.
- [x] 4.3 Return the response payload in the shape given in design.md §8: `search_window` with `*_source`, `keywords_searched`, `filters`, `complete`, `skipped`, `errors`, `message` and `jobs`. Also include `unrecognized_job_types` (`job_type`, `count`, `job_ids`, built from returned jobs only, in first-seen order) and a `notice` that names those types and asks the user to have the tool updated. Leave both out when there are none.

## 5. Tool

- [x] 5.1 Create `app/Services/Mcp/Tools/ChatBot/SearchInsightGlobalJobsTool.php`:
  - `#[Name('search-insight-global-jobs')]`;
  - a constructor taking `ToolContext`;
  - the schema from design.md §9;
  - a `#[Description]` that adapts the source prompt's presentation rules: state the date and days back used, one section per job, CSV or JSON in a labelled fenced block, never add a field that is not in the result, refine the remote label only from the description text, and pass on any `notice` to the user without translating the unrecognized job types;
  - `shouldRegister()` and a call-time guard, both using `User::find($context->userId)?->hasPermissionTo('edit-resume')` (design.md §3);
  - input validation that returns a correctable `Response::error`;
  - `Response::error` when every search failed, and `Response::structured` otherwise.
- [x] 5.2 Check discovery: `php artisan tinker --execute` constructs a `ChatBotToolRegistry` with `exposeAllDiscoveredTools: true` for a conversation and lists `search-insight-global-jobs` among its tool names. Then confirm the admin tool-listing endpoint returns the tool, through the feature test in 6.1.

## 6. Feature tests

- [x] 6.1 Write `tests/Feature/SearchInsightGlobalJobsToolTest.php` with `DatabaseTransactions` (never `RefreshDatabase`), `Http::fake()` with `Http::preventStrayRequests()`, `Sleep::fake(syncWithCarbon: true)` and `Carbon::setTestNow('2026-11-18 12:00:00')`. Assert the exact URLs sent with `Http::assertSent`. Cover:
  - the access gate: offered only with both the grant and `edit-resume`; hidden for a system without the grant, for no user, and for a user without the permission; an unauthorized call errors and sends nothing, checked with `Http::assertNothingSent`;
  - every scenario in "The search request carries only parameters that have values", including that the default query carries no `filter.locations`, that `Contract To Perm` is sent as `ContractToPerm` with no pay floor, and that an unknown requested type is refused with no request sent;
  - every scenario in "Unrecognized job types are reported for a tool update";
  - the fallback list and caller-supplied keywords;
  - the date window, including the reported defaults and supplied values;
  - paging both pages, the window-edge stop, and deduplication with `matched_keywords`;
  - details: the known-headings split, a 404 kept with `details_error`, and details turned off;
  - `max_results` capping;
  - spacing and an exhausted budget giving `complete: false` with `skipped`;
  - one keyword failing, every keyword failing, an unparseable body, a 3xx counted as a failure, and nothing in the window;
  - persisting nothing: a repeat call sends new requests, and no rows are written.

  Verify with `php artisan test --compact tests/Feature/SearchInsightGlobalJobsToolTest.php`.
- [x] 6.2 Change the fallback list to `vue`, `react`, `typescript` (`InsightGlobalSearchCriteria::FALLBACK_KEYWORDS`), and update the `keywords` schema description, which names the list. Add to the tool `#[Description]` that keywords should be single technology words, because multi-word and generic terms match almost every developer job. Then:
  - update `test_no_keyword_runs_the_fallback_list_in_order` to expect the three keywords;
  - add a test that "Front End" is sent as `front-end` and is the only keyword searched;
  - update the fallback list in `.claude/context/applications.md` if it is named there.

  Verify with `php artisan test --compact tests/Feature/SearchInsightGlobalJobsToolTest.php`.
- [x] 6.3 Add the US-only filter (design.md §5a):
  - `InsightGlobalJobNormalizer::isUsJob()` with the state, DC and PR codes and the `\bINTL\b` title check;
  - a `usOnly` property on `InsightGlobalSearchCriteria`, reported in `filtersSummary()`;
  - exclusion in `InsightGlobalSearch` right after the window check, with an `excluded_non_us` count in the payload;
  - a `us_only` boolean in the tool's schema and validation, defaulting to true, plus a sentence in the tool description saying international jobs are left out unless it is false.

  Add unit tests for `isUsJob()`: a US state, a non-US state, a US address with "INTL", no state, and case and whitespace. Add a feature test for each scenario in "International jobs are excluded by default". Verify with `php artisan test --compact --filter='InsightGlobal'`.
- [x] 6.4 Add the US-only filter and the location probe findings to the *Job search tools* section of `.claude/context/applications.md`: only a city or ZIP works, remote jobs match on their listed city, and there is no country field. Verify with `grep -n "us_only" .claude/context/applications.md`.

## 7. Docs and review

- [x] 7.1 Add a *Job search tools* section to `.claude/context/applications.md` covering:
  - the tool's name, its two gates, and why it uses `hasPermissionTo` (worker gates);
  - the endpoints and the parts of the probed response shape it relies on;
  - the budget numbers and the 299 s worker timeout;
  - that it persists nothing;
  - where its files live, with the services kept outside the discovered tools tree.

  Verify with `grep -n "search-insight-global-jobs" .claude/context/applications.md`.
- [x] 7.2 Run the `laravel-reviewer` agent over the diff and address its findings. Then run `php artisan test --compact --filter='InsightGlobal|ToolGrants|AiToolsController'` and verify it is green. Ask the developer whether to run the full suite.
- [x] 7.3 Report to the developer that `AuthorizedResumeEditTool` and `AuthorizedResumeTool` gate with `$user->can()`, which Keystone leaves unregistered in the console, so in the production `queue:work` worker they may never register. Make no change to them in this change.

## 8. Deploy (developer-owned, do not run)

- [ ] 8.1 Developer: deploy, then restart the queue worker with `sudo supervisorctl restart <program>`.
- [ ] 8.2 Developer: grant `search-insight-global-jobs` on the intended AI system in the admin. Then ask its persona to search, and confirm it states the date and days back used and returns live listings.
