## Outcome: Abandoned — infeasible

Confirmed infeasible through a second, independent path beyond the Graph API block
recorded in 1.1-1.3: manually re-enabled the live `fb-comments` XFBML widget on
`the-great-brand-ambassador-scam` (the one post confirmed via `engagement.
comment_plugin_count` to hold 4 real comments) directly in `single.blade.php`, with
correct `appId`, `data-href`, and SDK version matching what was live when those
comments were posted (Jan 2021). The widget's iframe shell loaded successfully
(Facebook's own `feedback.php` bootstrap response, not an error), ruling out
app/config/appId problems - but the wrapping `<span>` Facebook generates around the
iframe was explicitly styled `width:0; height:0` by Facebook's own JS, even with
browser tracking/ad-blocking protections disabled. That is Facebook's client actively
choosing to render nothing, not a client-side blocking or timing issue.

Cross-checked against Facebook's own `developers.facebook.com/tools/comments/`
moderation tool (for site owners to view/manage Comments Plugin data directly) - the
tool itself now 404s in-app, consistent with Meta having retired it. Also checked
Wayback Machine snapshots of the post from 2021-2025: every snapshot captured only the
pre-render `<div class="fb-comments" ...></div>` placeholder, never the rendered
comment content, since the crawler doesn't execute the Facebook SDK.

Three independent avenues (Graph API read, live widget render, moderation tool) and
one archival check all reach the same wall. **Decision: abandon this change.** The 4
comments on `the-great-brand-ambassador-scam` are not recoverable through any means
available to this project; Meta has withdrawn third-party and even self-service admin
access to legacy Comments Plugin data. `single.blade.php` was reverted to its
pre-change state (temporary widget snippet removed). No code from sections 2-4 was
built, since 1.3 correctly gated on feasibility before that work began.

## 1. Availability Check (feasibility gate)

- [x] 1.1 Obtain (or confirm existing) Facebook App credentials and a Page access token
      with permission to read Comments Plugin data for the site's domain; verify by
      making one manual Graph API request and getting a 200 response
      — Done. App `695473097788503` / `FB_APP_ID` confirmed as the correct app (its id
      and the admin's FB user id were embedded via `fb:app_id`/`fb:admins` meta tags on
      every blog post from commit `9438516`, 2021-10-31, until the widget's removal).
      Tried an app access token (`app_id|app_secret`) and a User Access Token (via
      Graph API Explorer, scope `public_profile`) against `GET
      https://graph.facebook.com/v21.0/` with `id=<post URL>`. Plain object lookup and
      the `engagement` field both return 200; see 1.2.
- [x] 1.2 Manually query the Graph API comments edge for 2-3 known old blog posts (a
      mix of early/late in the Facebook-comments era) using their current `route()`
      URL, and record for each: whether any comments are returned, and whether the URL
      needed to retrieve them differs from today's canonical URL
      — Done, but the gate result differs from what this task anticipated. `fields=comments`
      (and the `/comments` edge) fails identically for *every* post, including one
      confirmed to hold real comments — `400 (#100) Tried accessing nonexisting field
      (comments)` — across both token types and API versions v2.9 through v21.0.
      `fields=engagement` (`comment_plugin_count`) *does* work and is publicly
      readable: queried all 28 published posts, 27 report `comment_plugin_count: 0`;
      one, `the-great-brand-ambassador-scam` (2021-01-24), reports `4`. The
      `blog/{slug}` URL pattern is unchanged since 2020 (confirmed against the
      commit-2f45777-era `routes/web.php`), so no URL mismatch exists — the block is
      purely on the `comments` field itself, not on locating the right object.
- [x] 1.3 Decide and document (in this file or a follow-up note) whether the import is
      feasible: if zero posts return any comments, stop here and report back rather
      than building the importer — verify by writing the finding down and confirming
      with the user before proceeding to section 2
      — **Not feasible as scoped.** Meta has deprecated third-party programmatic read
      access to actual Comments Plugin comment content (message/author/timestamp) —
      only the aggregate `engagement.comment_plugin_count` metric remains publicly
      exposed via the Graph API, with no credential type available to this project
      (app token, user token) able to read the `comments` field, even for the one post
      that demonstrably has content behind it. This is a platform restriction on the
      data, not a permissions/setup gap that a different token or Page role would fix.
      Reported to the user; awaiting direction rather than proceeding to section 2.

## 2. Configuration

- [ ] 2.1 Add `services.facebook.app_id` / `app_secret` / `page_access_token` (or
      equivalent) to `config/services.php`, sourced from `.env` per the
      no-`env()`-outside-config convention; verify with
      `php artisan config:show services.facebook`
- [ ] 2.2 Add the corresponding keys to `.env.example` with placeholder values; verify
      by diffing against `.env`

## 3. Graph API Client

- [ ] 3.1 Implement a small service (e.g. `App\Services\Facebook\GraphCommentsClient`)
      that, given a post URL, fetches all comments from the Graph API comments edge
      (handling pagination) and returns them as plain DTOs/arrays with `id`, `parent_id`,
      `from.name`, `message`, `created_time`; verify with a unit test using a faked
      HTTP response (`Http::fake()`)
- [ ] 3.2 Handle and surface Graph API error responses (rate limit, invalid/expired
      token, object not found) as a typed exception distinct from "no comments found";
      verify with a unit test asserting the exception type for a faked error response

## 4. Import Command

- [ ] 4.1 Create `php artisan comments:import-facebook [--post=<slug>]` that iterates
      published Canvas posts (or the single post given via `--post`) and calls the
      Graph API client for each; verify by running against a faked HTTP client in a
      feature test and asserting it visits every published post
- [ ] 4.2 For each post's returned comments, create top-level comments first (no
      `parent_id`), skipping any `fb_comment_id` already present in `comments`; verify
      with a feature test asserting a `Comment` row is created with `fb_comment_id`,
      `name`, `message`, `created_at`/`updated_at` matching the Graph API payload, and
      `approved_at` equal to `created_at`
- [ ] 4.3 In a second pass, create reply comments, resolving `parent_id` by matching
      the Graph API parent id against `fb_comment_id` values created for that post in
      step 4.2, and computing `depth` the same way `CommentController::store()` does
      (`parent->depth + 1`); verify with a feature test asserting a reply's `parent_id`
      and `depth` are correct
- [ ] 4.4 Fall back to importing as a top-level comment (no `parent_id`, `depth` 0) when
      the parent can't be resolved or would exceed `Comment::MAX_DEPTH`; verify with a
      feature test covering both cases (missing parent id in the payload; parent chain
      that would exceed max depth)
- [ ] 4.5 Re-running the command for a post that was already imported creates no
      duplicate rows; verify with a feature test that runs the command twice against
      the same faked payload and asserts the comment count is unchanged after the
      second run
- [ ] 4.6 A Graph API failure for one post is logged/reported and does not stop the
      command from processing the remaining posts; verify with a feature test where one
      post's faked request errors and a later post's still succeeds

## 5. Verification

- [ ] 5.1 Run the command against a small real sample (1-2 posts) using the real Page
      access token from section 1, and manually confirm on the live blog post page that
      imported comments render correctly, threaded, and visible; document actual
      results (including any post-URL mismatches found in 1.2) before running against
      the full post history
- [ ] 5.2 Run `php artisan test --compact --filter=ImportFacebookComments` (or the
      equivalent filter matching the new test class names) and confirm all new tests
      pass
