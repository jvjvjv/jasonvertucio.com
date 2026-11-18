## Purpose

Gives an authorized AI persona one tool that searches Insight Global's public job listings for a date window, fetches each listing's details, and returns normalized results it can present without guessing. It replaces a prompt-driven, multi-step web search.

## ADDED Requirements

### Requirement: The tool is granted per AI system and gated on edit-resume

The system SHALL provide a chat-bot tool named `search-insight-global-jobs`. A persona SHALL be offered it only when both of these hold:

- the conversation's AI system includes the tool in its allowed tools;
- the conversation's user holds the `edit-resume` permission.

The permission check SHALL give the same answer in a queued worker as in a web request. When either condition fails, the tool SHALL NOT be offered. A call made anyway SHALL return an error and SHALL send no request to Insight Global. The tool SHALL appear in the admin tool picker with the other internal tools.

#### Scenario: Offered to an authorized user on a granting system

- **WHEN** a conversation's AI system allows `search-insight-global-jobs` and the conversation's user holds `edit-resume`
- **THEN** the tool is among the tools offered to the persona

#### Scenario: Hidden when the system does not grant it

- **WHEN** a conversation's AI system does not include `search-insight-global-jobs` in its allowed tools
- **THEN** the tool is not offered, whatever the user's permissions

#### Scenario: Hidden from anonymous or unauthorized users

- **WHEN** the AI system allows the tool, but the conversation has no user, or the user lacks `edit-resume`
- **THEN** the tool is not offered

#### Scenario: An unauthorized call is refused without network traffic

- **WHEN** the tool is invoked for a conversation whose user lacks `edit-resume`
- **THEN** it returns an error and no request is sent to Insight Global

### Requirement: The search request carries only parameters that have values

Each search request SHALL be an HTTP GET to `https://insightglobal.com/all/jobs` with the header `Accept: application/json`. All query values SHALL be URL-encoded, and a parameter with no value SHALL be left out entirely rather than sent empty.

- `keyword` SHALL be the search term in lowercase, with runs of whitespace replaced by single hyphens (for example, "Twilio Developer" is sent as `twilio-developer`).
- `page` SHALL start at 1. `size` SHALL be 10.
- `sort` SHALL default to `postedDate,desc`. The caller MAY give one or more sort values from `relevance,desc`, `postedDate,desc`, `payRate.max,asc` and `payRate.min,asc`, and each SHALL be sent as its own repeated `sort` parameter.
- `filter.status` SHALL be `active`.
- The caller MAY request the job types `Contract`, `Perm` and `Contract To Perm`, and any other value SHALL be refused with an error. Each requested type SHALL be sent as its own repeated `filter.jobType` parameter. The value sent is `Contract` for `Contract`, `Perm` for `Perm`, and `ContractToPerm` for `Contract To Perm`.
- `filter.includeOnlyRemoteWork=true` SHALL be sent only when the caller asks for remote-only work.
- `filter.minimumPayRateGreaterThan` SHALL be the caller's value when given. Otherwise it SHALL be 70 when the only job type is `Contract`, 130000 when the only job type is `Perm`, and left out in every other case, including when `Contract To Perm` is the only type. It SHALL never be sent without at least one `filter.jobType`.
- `filter.locations` SHALL be sent only when the caller gives a location, as that location lowercased. With no location it SHALL be left out, never sent as `all`.
- `filter.distance` SHALL be sent only when a specific location is given and the search is not remote-only. Its value SHALL be the caller's distance rounded to a multiple of 5 miles, or 10 by default.

#### Scenario: Defaults only

- **WHEN** the tool runs for the keyword "vue" with no other inputs
- **THEN** the first request is `GET https://insightglobal.com/all/jobs` with `keyword=vue`, `page=1`, `size=10`, `sort=postedDate,desc` and `filter.status=active`, and no `filter.jobType`, `filter.includeOnlyRemoteWork`, `filter.minimumPayRateGreaterThan`, `filter.locations` or `filter.distance`

#### Scenario: Multi-word keyword

- **WHEN** the keyword is "Twilio Developer"
- **THEN** the request sends `keyword=twilio-developer`

#### Scenario: Contract-only search gets the contract pay floor

- **WHEN** the only job type requested is `Contract` and no minimum pay rate is given
- **THEN** the request sends `filter.jobType=Contract` and `filter.minimumPayRateGreaterThan=70`

#### Scenario: Perm-only search gets the perm pay floor

- **WHEN** the only job type requested is `Perm` and no minimum pay rate is given
- **THEN** the request sends `filter.jobType=Perm` and `filter.minimumPayRateGreaterThan=130000`

#### Scenario: Contract To Perm is sent in the API's spelling

- **WHEN** the only job type requested is `Contract To Perm` and no minimum pay rate is given
- **THEN** the request sends `filter.jobType=ContractToPerm` and no `filter.minimumPayRateGreaterThan`

#### Scenario: An unknown requested job type is refused

- **WHEN** the caller requests the job type `Temp`
- **THEN** the tool returns an error naming the accepted job types and sends no request

#### Scenario: Several job types get no pay floor

- **WHEN** the job types `Contract` and `Perm` are both requested and no minimum pay rate is given
- **THEN** the request repeats `filter.jobType` for each type and sends no `filter.minimumPayRateGreaterThan`

#### Scenario: Remote-only search omits distance

- **WHEN** remote-only is requested along with the location "Philadelphia"
- **THEN** the request sends `filter.includeOnlyRemoteWork=true` and `filter.locations=philadelphia`, and no `filter.distance`

#### Scenario: Local search sends distance

- **WHEN** the location "Philadelphia" is given without remote-only and with no distance
- **THEN** the request sends `filter.locations=philadelphia` and `filter.distance=10`

#### Scenario: Repeated sort values

- **WHEN** the caller asks to sort by `payRate.max,asc` and then `payRate.min,asc`
- **THEN** the request carries two `sort` parameters, in that order

### Requirement: No keyword runs the fallback keyword list

When the caller gives no keyword, the tool SHALL search each of these keywords in order: `vue`, `react`, `typescript`. Every entry SHALL be a single word, because Insight Global matches a multi-word keyword against almost every developer job. When the caller gives one or more keywords, only those SHALL be searched, in the order given. A caller's multi-word keyword SHALL still be accepted and encoded as usual. The response SHALL list the keywords actually searched.

#### Scenario: Fallback list

- **WHEN** the tool is called with no keyword
- **THEN** it searches `vue`, `react` and `typescript`, in that order, and reports those three keywords

#### Scenario: A caller's multi-word keyword is still sent

- **WHEN** the tool is called with the keyword "Front End"
- **THEN** it searches only `front-end`

#### Scenario: Caller's keywords replace the fallback

- **WHEN** the tool is called with the keyword "angular"
- **THEN** it searches only `angular`

### Requirement: Results are limited to a date window that is always reported

The tool SHALL accept an optional `today` date and an optional `days_back` count. When `today` is not given, it SHALL use the server's current date. When `days_back` is not given, it SHALL use 7. A job SHALL be included only when its posted date falls on or after the start of the day `days_back` days before `today`, and no later than the end of `today`. The response SHALL always report the `today` and `days_back` values used and whether each was supplied or defaulted, so the persona can state them.

#### Scenario: Defaults are reported

- **WHEN** the tool is called on 2026-11-18 with neither `today` nor `days_back`
- **THEN** it keeps jobs posted from 2026-11-11 through 2026-11-18, and reports `today` 2026-11-18 (defaulted) and `days_back` 7 (defaulted)

#### Scenario: Older jobs are excluded

- **WHEN** a search page contains a job posted before the window starts
- **THEN** that job is not in the results

#### Scenario: Supplied values are reported as supplied

- **WHEN** the tool is called with `today` 2026-11-10 and `days_back` 3
- **THEN** it keeps jobs posted from 2026-11-07 through 2026-11-10, and reports both values as supplied

### Requirement: Every page is visited and duplicates are removed

For each keyword, the tool SHALL keep requesting the next page until the last page reported by the response's page metadata has been fetched, or until a page returns no jobs. When sorting by `postedDate,desc` alone, it SHALL stop paging a keyword once a page's last job is older than the window, because later pages can only be older. A job SHALL appear at most once in the results, matched by its requisition id, even when several keywords or pages return it. The response SHALL list, for each result, the keywords that matched it.

#### Scenario: Two pages are both fetched

- **WHEN** a keyword's first page reports two total pages and every job on page 1 is inside the window
- **THEN** the tool also requests page 2 and includes its in-window jobs

#### Scenario: Paging stops at the window edge

- **WHEN** the sort is `postedDate,desc` and the last job on page 1 is older than the window
- **THEN** page 2 is not requested

#### Scenario: The same job from two keywords

- **WHEN** the keywords `vue` and `react` both return the job with requisition id X
- **THEN** X appears once in the results, with both keywords listed as matches

### Requirement: Each result is enriched from its details endpoint

For each job in the results, the tool SHALL request `https://insightglobal.com/all/jobs/{requisitionId}/details` with `Accept: application/json`, unless the caller turns details off. The HTML description SHALL be turned into plain text with line breaks kept. When the description contains the headings "Required Skills & Experience", "Nice to Have Skills & Experience" or "Job Description", the text under each SHALL be returned as required skills, preferred skills and job description respectively. When none of those headings is present, the whole text SHALL be returned as the job description. The details response's disclaimer SHALL NOT be returned. When a detail request fails, the job SHALL still be returned, without those fields and with the error recorded on that job.

#### Scenario: Description with known headings

- **WHEN** a job's details description contains "Required Skills & Experience", "Nice to Have Skills & Experience" and "Job Description" sections
- **THEN** the result carries each section's text in required skills, preferred skills and job description, with HTML tags removed and entities decoded

#### Scenario: Description without known headings

- **WHEN** a job's details description has none of the known headings
- **THEN** the whole plain-text description is returned as the job description

#### Scenario: One detail request fails

- **WHEN** the details request for one job returns HTTP 404
- **THEN** that job is still in the results, has no description fields, and records the error, and the other jobs are unaffected

#### Scenario: Details turned off

- **WHEN** the caller turns details off
- **THEN** no details endpoint is requested and the results carry only the search-level fields

### Requirement: Results are normalized and never invented

Each result SHALL be built only from fields present in the Insight Global responses. Where the API does not supply a value, the field SHALL be omitted or null, never inferred. Each result SHALL include:

- title;
- company, as "Insight Global";
- location, as `City, ST` from the work address, or whichever part is present;
- remote status: "Remote" when the job is marked remote, otherwise "On-site or hybrid";
- the raw job type;
- a normalized employment type, but only for the three known raw job types, matched ignoring case and surrounding whitespace: `Perm` becomes "Full Time", `Contract` stays "Contract", and `Contract To Perm` becomes "Contract to Hire". Any other raw job type SHALL have no employment type, and the tool SHALL NOT guess one;
- the posted date, in both ISO 8601 and `n/j/Y` form;
- the job id (the requisition id);
- salary, formatted from the pay rate. Hourly rates render as `$65–$75/hr`, annual rates as `$155,000–$225,000/yr`, and a rate with only a minimum as `$67.91/hr`. The raw pay rate fields are also returned;
- the job URL, `https://insightglobal.com/jobs/{requisitionId}`.

#### Scenario: Annual range

- **WHEN** a job's pay rate is min 155000, max 225000, type Annually
- **THEN** its salary is `$155,000–$225,000/yr`

#### Scenario: Hourly minimum only

- **WHEN** a job's pay rate is min 67.91 with no max, type Hourly
- **THEN** its salary is `$67.91/hr`

#### Scenario: No pay rate

- **WHEN** a job has no pay rate
- **THEN** its salary is omitted, not estimated

#### Scenario: Contract-to-perm type

- **WHEN** a job's type is "Contract To Perm"
- **THEN** its employment type is "Contract to Hire" and its raw job type is "Contract To Perm"

#### Scenario: Not-remote job

- **WHEN** a job is marked as not remote
- **THEN** its remote status is "On-site or hybrid", not "On-site"

### Requirement: Unrecognized job types are reported for a tool update

When a returned job's raw job type is not one of `Perm`, `Contract` or `Contract To Perm`, the job SHALL still be returned, with its raw job type and no employment type. The response SHALL list each distinct unrecognized raw value with the number of returned jobs that carry it and their job ids. It SHALL also carry a notice telling the persona to tell the user that the tool met job types it does not recognize, name them, and ask for the tool to be updated to handle them. When every returned job's type is recognized, the response SHALL carry neither the list nor the notice.

#### Scenario: A loose job type is reported

- **WHEN** a returned job has the raw job type "Contract, perm possible"
- **THEN** that job is in the results with `job_type` "Contract, perm possible" and no employment type, the response lists "Contract, perm possible" with its count and job id, and the notice asks for the tool to be updated

#### Scenario: Every job type is known

- **WHEN** every returned job's raw job type is `Perm`, `Contract` or `Contract To Perm`
- **THEN** the response has no unrecognized job types and no notice

#### Scenario: Only returned jobs are counted

- **WHEN** a job with an unrecognized type is found but falls outside the window or beyond `max_results`
- **THEN** it is not counted in the unrecognized job types

### Requirement: International jobs are excluded by default

The tool SHALL accept an optional `us_only` boolean, defaulting to true. While it is true, a job SHALL be kept only when both of these hold:

- its listed state (`workAddress.administrativeArea`, trimmed and compared ignoring case) is the two-letter code of a US state, DC or Puerto Rico;
- its title does not contain the word "INTL", ignoring case.

A job with no listed state SHALL be excluded, since it cannot be shown to be in the US. The rule SHALL apply to remote and non-remote jobs alike, and SHALL be applied before results are de-duplicated, capped or enriched, so excluded jobs cost no detail requests. The response SHALL report `us_only` in its filters and the number of distinct jobs excluded as `excluded_non_us`. While `us_only` is false, no job SHALL be excluded on location and `excluded_non_us` SHALL be 0.

#### Scenario: A job outside the US is excluded

- **WHEN** a result is listed at Bengaluru, KA
- **THEN** it is not returned, and `excluded_non_us` counts it

#### Scenario: An international job at a US address is excluded

- **WHEN** a result is listed at Atlanta, GA with the title "Sr. Frontend Software Engineer - INTL INDIA"
- **THEN** it is not returned

#### Scenario: A US job is kept

- **WHEN** a result is listed at Philadelphia, PA with no "INTL" in its title
- **THEN** it is returned

#### Scenario: A remote job outside the US is excluded

- **WHEN** remote-only is requested and a remote result is listed at São Paulo, São Paulo
- **THEN** it is not returned

#### Scenario: A job with no listed state is excluded

- **WHEN** a result has no `workAddress.administrativeArea`
- **THEN** it is not returned while `us_only` is true

#### Scenario: Turning the filter off keeps international jobs

- **WHEN** `us_only` is false
- **THEN** jobs listed outside the US are returned, and `excluded_non_us` is 0

#### Scenario: Excluded jobs cost no detail requests

- **WHEN** a search returns two US jobs and one job listed at Toronto, ON
- **THEN** details are requested only for the two US jobs

### Requirement: The result count is capped and reported

The tool SHALL accept an optional `max_results` from 1 to 50, defaulting to 25. Results SHALL be ordered newest first by posted date. When more in-window jobs were found than the cap allows, the tool SHALL return the newest `max_results` and report that results were capped, with the total found.

#### Scenario: More jobs than the cap

- **WHEN** 30 distinct in-window jobs are found and `max_results` is 25
- **THEN** the 25 newest are returned and the response reports it was capped, with 30 found

### Requirement: Requests are spaced and the run stays inside a fixed budget

The tool SHALL wait at least 2 seconds between any two consecutive requests to Insight Global. It SHALL stop sending requests once a fixed request count or a fixed elapsed-time budget is reached, whichever comes first, and both SHALL be well under the queue worker's 299-second job timeout. A run cut short SHALL return the results gathered so far, mark itself incomplete, and say which keywords, pages or details were not fetched.

#### Scenario: Spacing between calls

- **WHEN** a run sends three requests
- **THEN** the tool waits at least 2 seconds before the second request and again before the third

#### Scenario: Budget exhausted

- **WHEN** the request budget runs out before every keyword and detail has been fetched
- **THEN** the tool returns what it gathered, marks the run incomplete, and lists what was skipped

### Requirement: Failures and empty results are reported plainly

When a search request fails, whether from an HTTP error status, a connection failure or a body that is not JSON with a `jobs` array, the tool SHALL report the error for that keyword with the status or reason and continue with the remaining keywords. When every search request fails, the tool SHALL return an error response naming the failures. When the searches succeed but no job falls in the window, the tool SHALL return an empty result list with the message "No matching jobs were found." The tool SHALL NOT follow redirects.

#### Scenario: One keyword fails

- **WHEN** the search for `react` returns HTTP 500 and the other keywords succeed
- **THEN** results from the other keywords are returned, and the response records that `react` failed with status 500

#### Scenario: Everything fails

- **WHEN** every search request fails
- **THEN** the tool returns an error response that names each keyword's failure

#### Scenario: Nothing in the window

- **WHEN** the searches succeed but return no job inside the window
- **THEN** the response has no results and the message "No matching jobs were found."

#### Scenario: Unparseable body

- **WHEN** a search response is not JSON or has no `jobs` array
- **THEN** that keyword is recorded as failed, with a reason saying the response could not be parsed

### Requirement: The tool persists nothing

The tool SHALL NOT create or change any database record, cache entry or AI memory itself, and SHALL NOT keep results between calls. Each call SHALL fetch fresh data. The conversation's existing record of tool calls and results falls outside this rule and is unchanged.

#### Scenario: Repeat call fetches again

- **WHEN** the tool is called twice with the same inputs
- **THEN** the second call sends its own requests to Insight Global, and neither call wrote any database row or cache entry of its own
