<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\JobSearch\InsightGlobal\InsightGlobalClient;
use App\Services\JobSearch\InsightGlobal\InsightGlobalJobNormalizer;
use App\Services\JobSearch\InsightGlobal\InsightGlobalSearch;
use App\Services\JobSearch\InsightGlobal\RequestBudget;
use App\Services\Mcp\Tools\ChatBot\SearchInsightGlobalJobsTool;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Jvjvjv\CodeTalker\Models\AiConversation;
use Jvjvjv\CodeTalker\Services\Mcp\ChatBotToolRegistry;
use Jvjvjv\CodeTalker\Services\Mcp\ToolResultConverter;
use Jvjvjv\CodeTalker\Support\ToolContext;
use Laravel\Mcp\Request;
use Tests\TestCase;

class SearchInsightGlobalJobsToolTest extends TestCase
{
    use DatabaseTransactions;

    private const JOB_A = '62ce0900-21e6-45b9-a786-96050a3843cb';

    private const JOB_B = '7fa0fb34-2bdc-4dcb-bb70-d83322892684';

    private const JOB_C = '8de6809f-fa96-48fb-8584-803812064032';

    private const JOB_D = '6cd6c6ed-018c-4bf6-95e7-170c9800ad7c';

    private const JOB_E = '2765bcfc-26c3-4550-aaa3-a214fb54300d';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-18 12:00:00');
        Sleep::fake(syncWithCarbon: true);
        Http::preventStrayRequests();

        Permission::firstOrCreate(['name' => 'edit-resume']);
        $this->user = User::factory()->create();
        $this->user->givePermissionTo('edit-resume');
    }

    public function test_the_registry_offers_the_tool_only_when_the_system_grants_it(): void
    {
        $conversation = new AiConversation(['user_id' => $this->user->id]);

        $granted = new ChatBotToolRegistry($conversation, ['search-insight-global-jobs']);
        $notGranted = new ChatBotToolRegistry($conversation, ['get-site-info']);

        $this->assertContains('search-insight-global-jobs', array_column($granted->toApiTools(), 'name'));
        $this->assertNotContains('search-insight-global-jobs', array_column($notGranted->toApiTools(), 'name'));
    }

    public function test_it_registers_only_for_a_user_with_edit_resume(): void
    {
        $withoutPermission = User::factory()->create();

        $this->assertTrue((new SearchInsightGlobalJobsTool(ToolContext::forUser($this->user->id)))->shouldRegister());
        $this->assertFalse((new SearchInsightGlobalJobsTool(ToolContext::forUser(null)))->shouldRegister());
        $this->assertFalse((new SearchInsightGlobalJobsTool(ToolContext::forUser($withoutPermission->id)))->shouldRegister());
    }

    public function test_an_unauthorized_call_is_refused_without_network_traffic(): void
    {
        $this->fakeInsightGlobal();
        $withoutPermission = User::factory()->create();

        $anonymous = $this->callTool([], null);
        $unauthorized = $this->callTool([], $withoutPermission);

        $this->assertSame('This tool requires resume-editing access.', $anonymous['error']);
        $this->assertSame('This tool requires resume-editing access.', $unauthorized['error']);
        Http::assertNothingSent();
    }

    public function test_the_admin_tool_listing_includes_the_tool_as_internal(): void
    {
        Permission::firstOrCreate(['name' => 'manage-ai-tools']);
        $this->user->givePermissionTo('manage-ai-tools');

        $this->actingAs($this->user)
            ->getJson(route('admin.ai.bots.mcp-tools', ['include_all' => 1]))
            ->assertOk()
            ->assertJsonFragment(['name' => 'search-insight-global-jobs', 'source' => 'internal']);
    }

    public function test_a_default_query_sends_only_parameters_with_values(): void
    {
        $this->fakeInsightGlobal();

        $this->callTool(['keywords' => ['vue']]);

        $this->assertSame([
            ['keyword', 'vue'],
            ['page', '1'],
            ['size', '10'],
            ['sort', 'postedDate,desc'],
            ['filter.status', 'active'],
        ], $this->searchQueries()[0]);
        $this->assertStringStartsWith('https://insightglobal.com/all/jobs?', $this->searchRequests()[0]->url());
        $this->assertSame(['application/json'], $this->searchRequests()[0]->header('Accept'));
    }

    public function test_a_multi_word_keyword_is_hyphenated_and_lowercased(): void
    {
        $this->fakeInsightGlobal();

        $result = $this->callTool(['keywords' => ['Twilio  Developer']]);

        $this->assertSame('twilio-developer', $this->param($this->searchQueries()[0], 'keyword'));
        $this->assertSame(['twilio-developer'], $result['keywords_searched']);
    }

    public function test_a_contract_only_search_gets_the_contract_pay_floor(): void
    {
        $this->fakeInsightGlobal();

        $this->callTool(['keywords' => ['vue'], 'job_types' => ['Contract']]);

        $query = $this->searchQueries()[0];
        $this->assertSame(['Contract'], $this->params($query, 'filter.jobType'));
        $this->assertSame('70', $this->param($query, 'filter.minimumPayRateGreaterThan'));
    }

    public function test_a_perm_only_search_gets_the_perm_pay_floor(): void
    {
        $this->fakeInsightGlobal();

        $this->callTool(['keywords' => ['vue'], 'job_types' => ['Perm']]);

        $query = $this->searchQueries()[0];
        $this->assertSame(['Perm'], $this->params($query, 'filter.jobType'));
        $this->assertSame('130000', $this->param($query, 'filter.minimumPayRateGreaterThan'));
    }

    public function test_contract_to_perm_is_sent_in_the_api_spelling_without_a_pay_floor(): void
    {
        $this->fakeInsightGlobal();

        $this->callTool(['keywords' => ['vue'], 'job_types' => ['Contract To Perm']]);

        $query = $this->searchQueries()[0];
        $this->assertSame(['ContractToPerm'], $this->params($query, 'filter.jobType'));
        $this->assertNull($this->param($query, 'filter.minimumPayRateGreaterThan'));
    }

    public function test_an_unknown_requested_job_type_is_refused_without_a_request(): void
    {
        $this->fakeInsightGlobal();

        $result = $this->callTool(['keywords' => ['vue'], 'job_types' => ['Temp']]);

        $this->assertStringContainsString('Contract, Perm, Contract To Perm', $result['error']);
        Http::assertNothingSent();
    }

    public function test_several_job_types_repeat_the_filter_and_get_no_pay_floor(): void
    {
        $this->fakeInsightGlobal();

        $this->callTool(['keywords' => ['vue'], 'job_types' => ['Contract', 'Perm']]);

        $query = $this->searchQueries()[0];
        $this->assertSame(['Contract', 'Perm'], $this->params($query, 'filter.jobType'));
        $this->assertNull($this->param($query, 'filter.minimumPayRateGreaterThan'));
    }

    public function test_a_given_minimum_pay_rate_is_sent_only_with_a_job_type(): void
    {
        $this->fakeInsightGlobal();

        $this->callTool(['keywords' => ['vue'], 'job_types' => ['Contract'], 'minimum_pay_rate' => 82.5]);
        $this->callTool(['keywords' => ['react'], 'minimum_pay_rate' => 82.5]);

        $this->assertSame('82.5', $this->param($this->searchQueries()[0], 'filter.minimumPayRateGreaterThan'));
        $this->assertNull($this->param($this->searchQueries()[1], 'filter.minimumPayRateGreaterThan'));
    }

    public function test_a_remote_only_search_omits_distance(): void
    {
        $this->fakeInsightGlobal();

        $this->callTool(['keywords' => ['vue'], 'remote_only' => true, 'location' => 'Philadelphia']);

        $query = $this->searchQueries()[0];
        $this->assertSame('true', $this->param($query, 'filter.includeOnlyRemoteWork'));
        $this->assertSame('philadelphia', $this->param($query, 'filter.locations'));
        $this->assertNull($this->param($query, 'filter.distance'));
    }

    public function test_a_local_search_sends_a_distance_rounded_to_five(): void
    {
        $this->fakeInsightGlobal();

        $this->callTool(['keywords' => ['vue'], 'location' => 'Philadelphia']);
        $this->callTool(['keywords' => ['react'], 'location' => 'Philadelphia', 'distance' => 13]);

        $this->assertSame('philadelphia', $this->param($this->searchQueries()[0], 'filter.locations'));
        $this->assertSame('10', $this->param($this->searchQueries()[0], 'filter.distance'));
        $this->assertSame('15', $this->param($this->searchQueries()[1], 'filter.distance'));
        $this->assertNull($this->param($this->searchQueries()[0], 'filter.includeOnlyRemoteWork'));
    }

    public function test_repeated_sort_values_are_sent_in_order(): void
    {
        $this->fakeInsightGlobal();

        $this->callTool(['keywords' => ['vue'], 'sort' => ['payRate.max,asc', 'payRate.min,asc']]);

        $this->assertSame(['payRate.max,asc', 'payRate.min,asc'], $this->params($this->searchQueries()[0], 'sort'));
        $this->assertStringContainsString('sort=payRate.max%2Casc&sort=payRate.min%2Casc', $this->searchRequests()[0]->url());
    }

    public function test_no_keyword_runs_the_fallback_list_in_order(): void
    {
        $this->fakeInsightGlobal();

        $result = $this->callTool([]);

        $expected = ['vue', 'react', 'typescript'];
        $this->assertSame($expected, array_map(fn (array $query): ?string => $this->param($query, 'keyword'), $this->searchQueries()));
        $this->assertSame($expected, $result['keywords_searched']);
    }

    public function test_a_callers_multi_word_keyword_is_still_sent(): void
    {
        $this->fakeInsightGlobal();

        $result = $this->callTool(['keywords' => ['Front End']]);

        $this->assertSame(['front-end'], array_map(fn (array $query): ?string => $this->param($query, 'keyword'), $this->searchQueries()));
        $this->assertSame(['front-end'], $result['keywords_searched']);
    }

    public function test_the_callers_keywords_replace_the_fallback(): void
    {
        $this->fakeInsightGlobal();

        $result = $this->callTool(['keywords' => ['angular']]);

        $this->assertCount(1, $this->searchQueries());
        $this->assertSame(['angular'], $result['keywords_searched']);
    }

    public function test_the_default_window_is_reported_and_older_jobs_are_excluded(): void
    {
        $this->fakeInsightGlobal(['vue' => [1 => $this->fixture('search-page-1.json'), 2 => $this->fixture('search-page-2.json')]]);

        $result = $this->callTool(['keywords' => ['vue'], 'include_details' => false]);

        $this->assertSame('2026-11-18', $result['search_window']['today']);
        $this->assertSame('default', $result['search_window']['today_source']);
        $this->assertSame(7, $result['search_window']['days_back']);
        $this->assertSame('default', $result['search_window']['days_back_source']);
        $this->assertSame('2026-11-11T00:00:00+00:00', $result['search_window']['from']);
        $this->assertSame([self::JOB_A, self::JOB_B, self::JOB_C, self::JOB_D], array_column($result['jobs'], 'job_id'));
    }

    public function test_supplied_window_values_are_used_and_reported_as_supplied(): void
    {
        $this->fakeInsightGlobal(['vue' => [1 => $this->fixture('search-page-1.json'), 2 => $this->fixture('search-page-2.json')]]);

        $result = $this->callTool(['keywords' => ['vue'], 'today' => '2026-11-16', 'days_back' => 1, 'include_details' => false]);

        $this->assertSame('2026-11-16', $result['search_window']['today']);
        $this->assertSame('supplied', $result['search_window']['today_source']);
        $this->assertSame(1, $result['search_window']['days_back']);
        $this->assertSame('supplied', $result['search_window']['days_back_source']);
        $this->assertSame([self::JOB_B, self::JOB_C], array_column($result['jobs'], 'job_id'));
    }

    public function test_an_invalid_date_is_refused_with_a_correctable_message(): void
    {
        $this->fakeInsightGlobal();

        $result = $this->callTool(['today' => '11/18/2026']);

        $this->assertStringStartsWith('The search was not run.', $result['error']);
        Http::assertNothingSent();
    }

    public function test_blank_optional_inputs_are_treated_as_omitted(): void
    {
        $this->fakeInsightGlobal(['vue' => [1 => $this->singlePage()]]);

        $result = $this->callTool([
            'keywords' => ['vue'],
            'today' => '',
            'days_back' => '',
            'job_types' => ['Contract'],
            'minimum_pay_rate' => '',
            'location' => 'Philadelphia',
            'distance' => ' ',
            'include_details' => '',
            'max_results' => '',
        ]);

        $this->assertSame('default', $result['search_window']['today_source']);
        $this->assertSame('default', $result['search_window']['days_back_source']);
        $this->assertSame(7, $result['search_window']['days_back']);
        $query = $this->searchQueries()[0];
        $this->assertSame('70', $this->param($query, 'filter.minimumPayRateGreaterThan'));
        $this->assertSame('10', $this->param($query, 'filter.distance'));
        $this->assertCount(3, $result['jobs']);
        $this->assertCount(3, $this->detailRequests());
    }

    public function test_every_page_is_fetched_while_jobs_are_in_the_window(): void
    {
        $this->fakeInsightGlobal(['vue' => [1 => $this->fixture('search-page-1.json'), 2 => $this->fixture('search-page-2.json')]]);

        $this->callTool(['keywords' => ['vue'], 'include_details' => false]);

        $this->assertSame(['1', '2'], array_map(fn (array $query): ?string => $this->param($query, 'page'), $this->searchQueries()));
    }

    public function test_paging_stops_at_the_window_edge_when_sorted_newest_first(): void
    {
        $page = $this->fixture('search-page-1.json');
        $page['jobs'][2]['postedDate'] = '2026-11-01T10:00:00.1Z';
        $this->fakeInsightGlobal(['vue' => [1 => $page, 2 => $this->fixture('search-page-2.json')]]);

        $result = $this->callTool(['keywords' => ['vue'], 'include_details' => false]);

        $this->assertCount(1, $this->searchQueries());
        $this->assertSame([self::JOB_A, self::JOB_B], array_column($result['jobs'], 'job_id'));
    }

    public function test_paging_continues_past_old_jobs_under_another_sort(): void
    {
        $page = $this->fixture('search-page-1.json');
        $page['jobs'][2]['postedDate'] = '2026-11-01T10:00:00.1Z';
        $this->fakeInsightGlobal(['vue' => [1 => $page, 2 => $this->fixture('search-page-2.json')]]);

        $this->callTool(['keywords' => ['vue'], 'sort' => ['relevance,desc'], 'include_details' => false]);

        $this->assertCount(2, $this->searchQueries());
    }

    public function test_a_job_returned_by_two_keywords_appears_once_with_both_matches(): void
    {
        $page = $this->singlePage();
        $this->fakeInsightGlobal(['vue' => [1 => $page], 'react' => [1 => $page]]);

        $result = $this->callTool(['keywords' => ['vue', 'react'], 'include_details' => false]);

        $this->assertSame([self::JOB_A, self::JOB_B, self::JOB_C], array_column($result['jobs'], 'job_id'));
        $this->assertSame(['vue', 'react'], $result['jobs'][0]['matched_keywords']);
        $this->assertSame(3, $result['total_found']);
    }

    public function test_each_result_is_enriched_from_its_details(): void
    {
        $this->fakeInsightGlobal(['vue' => [1 => $this->singlePage()]]);

        $result = $this->callTool(['keywords' => ['vue']]);

        $this->assertCount(3, $this->detailRequests());
        $this->assertSame(['application/json'], $this->detailRequests()[0]->header('Accept'));
        $this->assertSame('https://insightglobal.com/all/jobs/'.self::JOB_A.'/details', $this->detailRequests()[0]->url());
        $this->assertStringStartsWith('10+ years of hands-on Python', $result['jobs'][0]['required_skills']);
        $this->assertStringStartsWith('Experience with React', $result['jobs'][0]['preferred_skills']);
        $this->assertStringContainsString('Product & Technology', $result['jobs'][0]['job_description']);
    }

    public function test_a_failed_detail_request_keeps_the_job_and_records_the_error(): void
    {
        $this->fakeInsightGlobal(
            ['vue' => [1 => $this->singlePage()]],
            [self::JOB_B => Http::response(['error' => 'not found'], 404)],
        );

        $result = $this->callTool(['keywords' => ['vue']]);

        $jobB = $result['jobs'][1];
        $this->assertSame(self::JOB_B, $jobB['job_id']);
        $this->assertSame('HTTP 404', $jobB['details_error']);
        $this->assertArrayNotHasKey('job_description', $jobB);
        $this->assertContains(['requisition_id' => self::JOB_B, 'status' => 404, 'reason' => 'HTTP 404'], $result['errors']);
        $this->assertArrayHasKey('job_description', $result['jobs'][0]);
        $this->assertArrayHasKey('job_description', $result['jobs'][2]);
    }

    public function test_details_can_be_turned_off(): void
    {
        $this->fakeInsightGlobal(['vue' => [1 => $this->singlePage()]]);

        $result = $this->callTool(['keywords' => ['vue'], 'include_details' => false]);

        $this->assertSame([], $this->detailRequests());
        $this->assertArrayNotHasKey('job_description', $result['jobs'][0]);
        $this->assertSame('$155,000–$225,000/yr', $result['jobs'][0]['salary']);
    }

    public function test_results_are_capped_newest_first_and_the_cap_is_reported(): void
    {
        $this->fakeInsightGlobal(['vue' => [1 => $this->fixture('search-page-1.json'), 2 => $this->fixture('search-page-2.json')]]);

        $result = $this->callTool(['keywords' => ['vue'], 'max_results' => 2]);

        $this->assertSame([self::JOB_A, self::JOB_B], array_column($result['jobs'], 'job_id'));
        $this->assertTrue($result['capped']);
        $this->assertSame(4, $result['total_found']);
        $this->assertSame(2, $result['returned']);
        $this->assertCount(2, $this->detailRequests());
    }

    public function test_requests_are_spaced_two_seconds_apart(): void
    {
        $this->fakeInsightGlobal(['vue' => [1 => $this->singlePage()]]);

        $this->callTool(['keywords' => ['vue']]);

        Http::assertSentCount(4);
        Sleep::assertSleptTimes(3);
        Sleep::assertSequence(array_fill(0, 3, Sleep::for(2)->seconds()));
    }

    public function test_an_exhausted_budget_returns_partial_results_marked_incomplete(): void
    {
        $this->fakeInsightGlobal(['vue' => [1 => $this->fixture('search-page-1.json'), 2 => $this->fixture('search-page-2.json')]]);

        $tool = new class(ToolContext::forUser($this->user->id)) extends SearchInsightGlobalJobsTool
        {
            protected function search(): InsightGlobalSearch
            {
                return new InsightGlobalSearch(
                    new InsightGlobalClient(new RequestBudget(maxRequests: 2), 'test-agent'),
                    new InsightGlobalJobNormalizer,
                );
            }
        };

        $result = ToolResultConverter::toArray($tool->handle(new Request(['keywords' => ['vue', 'react']])));

        Http::assertSentCount(2);
        $this->assertFalse($result['complete']);
        $this->assertContains('keyword react page 1', $result['skipped']);
        $this->assertContains('details '.self::JOB_A, $result['skipped']);
        $this->assertCount(4, $result['jobs']);
    }

    public function test_one_failed_keyword_is_reported_and_the_others_still_return(): void
    {
        $this->fakeInsightGlobal([
            'vue' => [1 => $this->singlePage()],
            'react' => [1 => Http::response('Server Error', 500)],
        ]);

        $result = $this->callTool(['keywords' => ['vue', 'react'], 'include_details' => false]);

        $this->assertCount(3, $result['jobs']);
        $this->assertSame([['keyword' => 'react', 'page' => 1, 'status' => 500, 'reason' => 'HTTP 500']], $result['errors']);
    }

    public function test_an_error_response_names_every_failure_when_all_searches_fail(): void
    {
        $this->fakeInsightGlobal([
            'vue' => [1 => Http::response('Server Error', 500)],
            'react' => [1 => Http::response('Bad Gateway', 502)],
        ]);

        $result = $this->callTool(['keywords' => ['vue', 'react']]);

        $this->assertSame('Every Insight Global search failed: vue: HTTP 500; react: HTTP 502.', $result['error']);
    }

    public function test_an_unparseable_body_is_recorded_as_a_failure(): void
    {
        $this->fakeInsightGlobal([
            'vue' => [1 => $this->singlePage()],
            'react' => [1 => Http::response('<html>not json</html>', 200)],
            'angular' => [1 => ['results' => []]],
        ]);

        $result = $this->callTool(['keywords' => ['vue', 'react', 'angular'], 'include_details' => false]);

        $this->assertSame('the response could not be parsed as JSON', $result['errors'][0]['reason']);
        $this->assertSame('react', $result['errors'][0]['keyword']);
        $this->assertSame('the response could not be parsed: it has no jobs array', $result['errors'][1]['reason']);
        $this->assertSame('angular', $result['errors'][1]['keyword']);
    }

    public function test_a_redirect_is_not_followed_and_counts_as_a_failure(): void
    {
        $this->fakeInsightGlobal([
            'vue' => [1 => $this->singlePage()],
            'react' => [1 => Http::response('', 301, ['Location' => 'https://example.com/elsewhere'])],
        ]);

        $result = $this->callTool(['keywords' => ['vue', 'react'], 'include_details' => false]);

        $this->assertSame(301, $result['errors'][0]['status']);
        $this->assertSame('the response was a redirect, which is not followed', $result['errors'][0]['reason']);
        Http::assertNotSent(fn (HttpRequest $request): bool => str_contains($request->url(), 'example.com'));
    }

    public function test_a_connection_failure_is_recorded(): void
    {
        $this->fakeInsightGlobal([
            'vue' => [1 => $this->singlePage()],
            'react' => [1 => Http::failedConnection()],
        ]);

        $result = $this->callTool(['keywords' => ['vue', 'react'], 'include_details' => false]);

        $this->assertStringStartsWith('the connection failed', $result['errors'][0]['reason']);
    }

    public function test_nothing_in_the_window_says_no_matching_jobs_were_found(): void
    {
        $page = $this->singlePage();
        foreach ($page['jobs'] as $index => $job) {
            $page['jobs'][$index]['postedDate'] = '2026-09-01T10:00:00.1Z';
        }
        $this->fakeInsightGlobal(['vue' => [1 => $page]]);

        $result = $this->callTool(['keywords' => ['vue']]);

        $this->assertSame([], $result['jobs']);
        $this->assertSame('No matching jobs were found.', $result['message']);
        $this->assertArrayNotHasKey('error', $result);
    }

    public function test_an_unrecognized_job_type_is_reported_for_a_tool_update(): void
    {
        $this->fakeInsightGlobal(['vue' => [1 => $this->fixture('search-page-1.json'), 2 => $this->fixture('search-page-2.json')]]);

        $result = $this->callTool(['keywords' => ['vue'], 'include_details' => false]);

        $jobD = collect($result['jobs'])->firstWhere('job_id', self::JOB_D);
        $this->assertSame('Contract, perm possible', $jobD['job_type']);
        $this->assertArrayNotHasKey('employment_type', $jobD);
        $this->assertSame(
            [['job_type' => 'Contract, perm possible', 'count' => 1, 'job_ids' => [self::JOB_D]]],
            $result['unrecognized_job_types'],
        );
        $this->assertStringContainsString('"Contract, perm possible"', $result['notice']);
        $this->assertStringContainsString('search-insight-global-jobs tool to be updated', $result['notice']);
    }

    public function test_known_job_types_produce_no_notice(): void
    {
        $this->fakeInsightGlobal(['vue' => [1 => $this->singlePage()]]);

        $result = $this->callTool(['keywords' => ['vue'], 'include_details' => false]);

        $this->assertArrayNotHasKey('unrecognized_job_types', $result);
        $this->assertArrayNotHasKey('notice', $result);
    }

    public function test_only_returned_jobs_count_toward_unrecognized_job_types(): void
    {
        $this->fakeInsightGlobal(['vue' => [1 => $this->fixture('search-page-1.json'), 2 => $this->fixture('search-page-2.json')]]);

        $result = $this->callTool(['keywords' => ['vue'], 'days_back' => 3, 'include_details' => false]);

        $this->assertNotContains(self::JOB_D, array_column($result['jobs'], 'job_id'));
        $this->assertArrayNotHasKey('unrecognized_job_types', $result);
        $this->assertArrayNotHasKey('notice', $result);
    }

    public function test_a_job_outside_the_us_is_excluded_and_counted(): void
    {
        $page = $this->singlePage();
        $page['jobs'][1]['workAddress'] = ['locality' => 'Bengaluru', 'administrativeArea' => 'KA'];
        $this->fakeInsightGlobal(['vue' => [1 => $page]]);

        $result = $this->callTool(['keywords' => ['vue'], 'include_details' => false]);

        $this->assertSame([self::JOB_A, self::JOB_C], array_column($result['jobs'], 'job_id'));
        $this->assertSame(1, $result['excluded_non_us']);
        $this->assertTrue($result['filters']['us_only']);
    }

    public function test_an_international_job_at_a_us_address_is_excluded(): void
    {
        $page = $this->singlePage();
        $page['jobs'][1]['jobTitle'] = 'Sr. Frontend Software Engineer - INTL INDIA';
        $page['jobs'][1]['workAddress'] = ['locality' => 'Atlanta', 'administrativeArea' => 'GA'];
        $this->fakeInsightGlobal(['vue' => [1 => $page]]);

        $result = $this->callTool(['keywords' => ['vue'], 'include_details' => false]);

        $this->assertNotContains(self::JOB_B, array_column($result['jobs'], 'job_id'));
        $this->assertSame(1, $result['excluded_non_us']);
    }

    public function test_a_us_job_is_kept(): void
    {
        $this->fakeInsightGlobal(['vue' => [1 => $this->fixture('search-page-1.json'), 2 => $this->fixture('search-page-2.json')]]);

        $result = $this->callTool(['keywords' => ['vue'], 'include_details' => false]);

        $jobD = collect($result['jobs'])->firstWhere('job_id', self::JOB_D);
        $this->assertSame('Philadelphia, PA', $jobD['location']);
        $this->assertSame(0, $result['excluded_non_us']);
    }

    public function test_a_remote_job_outside_the_us_is_excluded(): void
    {
        $page = $this->singlePage();
        $page['jobs'][0]['workAddress'] = ['locality' => 'São Paulo', 'administrativeArea' => 'São Paulo'];
        $this->fakeInsightGlobal(['vue' => [1 => $page]]);

        $result = $this->callTool(['keywords' => ['vue'], 'remote_only' => true, 'include_details' => false]);

        $this->assertNotContains(self::JOB_A, array_column($result['jobs'], 'job_id'));
        $this->assertSame(1, $result['excluded_non_us']);
    }

    public function test_a_job_with_no_listed_state_is_excluded(): void
    {
        $page = $this->singlePage();
        unset($page['jobs'][2]['workAddress']['administrativeArea']);
        $this->fakeInsightGlobal(['vue' => [1 => $page]]);

        $result = $this->callTool(['keywords' => ['vue'], 'include_details' => false]);

        $this->assertSame([self::JOB_A, self::JOB_B], array_column($result['jobs'], 'job_id'));
        $this->assertSame(1, $result['excluded_non_us']);
    }

    public function test_turning_the_us_filter_off_keeps_international_jobs(): void
    {
        $page = $this->singlePage();
        $page['jobs'][1]['workAddress'] = ['locality' => 'Bengaluru', 'administrativeArea' => 'KA'];
        unset($page['jobs'][2]['workAddress']['administrativeArea']);
        $this->fakeInsightGlobal(['vue' => [1 => $page]]);

        $result = $this->callTool(['keywords' => ['vue'], 'us_only' => false, 'include_details' => false]);

        $this->assertSame([self::JOB_A, self::JOB_B, self::JOB_C], array_column($result['jobs'], 'job_id'));
        $this->assertSame('Bengaluru, KA', $result['jobs'][1]['location']);
        $this->assertSame(0, $result['excluded_non_us']);
        $this->assertFalse($result['filters']['us_only']);
    }

    public function test_a_job_excluded_by_two_keywords_is_counted_once(): void
    {
        $page = $this->singlePage();
        $page['jobs'][1]['workAddress'] = ['locality' => 'Bengaluru', 'administrativeArea' => 'KA'];
        $this->fakeInsightGlobal(['vue' => [1 => $page], 'react' => [1 => $page]]);

        $result = $this->callTool(['keywords' => ['vue', 'react'], 'include_details' => false]);

        $this->assertSame(1, $result['excluded_non_us']);
    }

    public function test_excluded_jobs_cost_no_detail_requests(): void
    {
        $page = $this->singlePage();
        $page['jobs'][1]['workAddress'] = ['locality' => 'Toronto', 'administrativeArea' => 'ON'];
        $this->fakeInsightGlobal(['vue' => [1 => $page]]);

        $result = $this->callTool(['keywords' => ['vue']]);

        $this->assertSame(
            [
                'https://insightglobal.com/all/jobs/'.self::JOB_A.'/details',
                'https://insightglobal.com/all/jobs/'.self::JOB_C.'/details',
            ],
            array_map(static fn (HttpRequest $request): string => $request->url(), $this->detailRequests()),
        );
        $this->assertSame(2, $result['returned']);
    }

    public function test_it_persists_nothing_and_a_repeat_call_fetches_again(): void
    {
        $this->fakeInsightGlobal(['vue' => [1 => $this->singlePage()]]);
        Cache::spy();
        $writes = [];
        DB::listen(function ($query) use (&$writes): void {
            if (preg_match('/^\s*(insert|update|delete|replace)\b/i', $query->sql)) {
                $writes[] = $query->sql;
            }
        });

        $this->callTool(['keywords' => ['vue']]);
        $this->callTool(['keywords' => ['vue']]);

        Http::assertSentCount(8);
        $this->assertSame([], $writes);
        Cache::shouldNotHaveReceived('put');
        Cache::shouldNotHaveReceived('remember');
        Cache::shouldNotHaveReceived('forever');
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function callTool(array $input, User|false|null $user = false): array
    {
        $userId = $user === false ? $this->user->id : $user?->id;
        $tool = new SearchInsightGlobalJobsTool(ToolContext::forUser($userId));

        return ToolResultConverter::toArray($tool->handle(new Request($input)));
    }

    /**
     * Route faked requests: search pages by keyword and page (unmapped pages
     * are empty), details by requisition id (unmapped ids get the headings
     * fixture).
     *
     * @param  array<string, array<int, mixed>>  $search
     * @param  array<string, mixed>  $details
     */
    private function fakeInsightGlobal(array $search = [], array $details = []): void
    {
        Http::fake(function (HttpRequest $request) use ($search, $details) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);

            if (preg_match('#^/all/jobs/([^/]+)/details$#', $path, $matches)) {
                return $this->toResponse($details[rawurldecode($matches[1])] ?? $this->fixture('details-with-headings.json'));
            }

            $query = $this->queryPairs($request->url());
            $page = (int) $this->param($query, 'page');

            return $this->toResponse($search[$this->param($query, 'keyword')][$page] ?? $this->fixture('search-empty.json'));
        });
    }

    private function toResponse(mixed $value): mixed
    {
        return is_array($value) ? Http::response($value) : $value;
    }

    /**
     * Page 1 alone: jobs A, B and C, all in the default window.
     *
     * @return array<string, mixed>
     */
    private function singlePage(): array
    {
        $page = $this->fixture('search-page-1.json');
        $page['pageMetadata']['totalPages'] = 1;

        return $page;
    }

    /**
     * @return list<HttpRequest>
     */
    private function searchRequests(): array
    {
        return Http::recorded(fn (HttpRequest $request): bool => parse_url($request->url(), PHP_URL_PATH) === '/all/jobs')
            ->map(fn (array $pair): HttpRequest => $pair[0])
            ->values()
            ->all();
    }

    /**
     * @return list<HttpRequest>
     */
    private function detailRequests(): array
    {
        return Http::recorded(fn (HttpRequest $request): bool => str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/details'))
            ->map(fn (array $pair): HttpRequest => $pair[0])
            ->values()
            ->all();
    }

    /**
     * @return list<list<array{0: string, 1: string}>>
     */
    private function searchQueries(): array
    {
        return array_map(fn (HttpRequest $request): array => $this->queryPairs($request->url()), $this->searchRequests());
    }

    /**
     * Decode a query string into ordered pairs, keeping repeated keys.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function queryPairs(string $url): array
    {
        $query = (string) parse_url($url, PHP_URL_QUERY);

        if ($query === '') {
            return [];
        }

        return array_map(static function (string $part): array {
            [$name, $value] = array_pad(explode('=', $part, 2), 2, '');

            return [rawurldecode($name), rawurldecode($value)];
        }, explode('&', $query));
    }

    /**
     * @param  list<array{0: string, 1: string}>  $pairs
     */
    private function param(array $pairs, string $name): ?string
    {
        return $this->params($pairs, $name)[0] ?? null;
    }

    /**
     * @param  list<array{0: string, 1: string}>  $pairs
     * @return list<string>
     */
    private function params(array $pairs, string $name): array
    {
        return array_values(array_map(
            static fn (array $pair): string => $pair[1],
            array_filter($pairs, static fn (array $pair): bool => $pair[0] === $name),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function fixture(string $name): array
    {
        return json_decode(
            (string) file_get_contents(base_path('tests/Fixtures/insight-global/'.$name)),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }
}
