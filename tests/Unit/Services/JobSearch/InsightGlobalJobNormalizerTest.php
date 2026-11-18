<?php

namespace Tests\Unit\Services\JobSearch;

use App\Services\JobSearch\InsightGlobal\InsightGlobalJobNormalizer;
use PHPUnit\Framework\TestCase;

class InsightGlobalJobNormalizerTest extends TestCase
{
    private InsightGlobalJobNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->normalizer = new InsightGlobalJobNormalizer;
    }

    public function test_a_perm_remote_job_is_normalized(): void
    {
        $job = $this->normalizer->normalize($this->searchJob(1, 0), null, ['vue']);

        $this->assertSame('62ce0900-21e6-45b9-a786-96050a3843cb', $job['job_id']);
        $this->assertSame('Agentic Developer', $job['title']);
        $this->assertSame('Insight Global', $job['company']);
        $this->assertSame('Westlake, TX', $job['location']);
        $this->assertSame('Remote', $job['remote_status']);
        $this->assertTrue($job['remote']);
        $this->assertSame('Full Time', $job['employment_type']);
        $this->assertSame('Perm', $job['job_type']);
        $this->assertSame('11/17/2026', $job['posted_date']);
        $this->assertSame('2026-11-17T19:47:52+00:00', $job['posted_at']);
        $this->assertSame('https://insightglobal.com/jobs/62ce0900-21e6-45b9-a786-96050a3843cb', $job['job_url']);
        $this->assertSame(['vue'], $job['matched_keywords']);
    }

    public function test_an_annual_range_is_formatted_per_year(): void
    {
        $job = $this->normalizer->normalize($this->searchJob(1, 0));

        $this->assertSame('$155,000–$225,000/yr', $job['salary']);
        $this->assertSame(['min' => 155000, 'max' => 225000, 'currency' => 'USD', 'type' => 'Annually'], $job['pay_rate']);
    }

    public function test_an_hourly_minimum_only_is_formatted_without_a_range(): void
    {
        $job = $this->normalizer->normalize($this->searchJob(1, 1));

        $this->assertSame('$67.91/hr', $job['salary']);
        $this->assertSame('Contract', $job['employment_type']);
    }

    public function test_an_hourly_range_of_whole_numbers_drops_the_decimals(): void
    {
        $job = $this->normalizer->normalize($this->searchJob(1, 2));

        $this->assertSame('$65–$75/hr', $job['salary']);
    }

    public function test_a_job_without_a_pay_rate_has_no_salary(): void
    {
        $job = $this->normalizer->normalize($this->searchJob(2, 0));

        $this->assertArrayNotHasKey('salary', $job);
        $this->assertArrayNotHasKey('pay_rate', $job);
    }

    public function test_contract_to_perm_is_contract_to_hire_and_keeps_its_raw_type(): void
    {
        $job = $this->normalizer->normalize($this->searchJob(1, 2));

        $this->assertSame('Contract to Hire', $job['employment_type']);
        $this->assertSame('Contract To Perm', $job['job_type']);
    }

    public function test_a_not_remote_job_is_on_site_or_hybrid(): void
    {
        $job = $this->normalizer->normalize($this->searchJob(1, 1));

        $this->assertSame('On-site or hybrid', $job['remote_status']);
        $this->assertFalse($job['remote']);
    }

    public function test_an_unrecognized_job_type_has_no_employment_type_and_is_flagged(): void
    {
        $entry = $this->searchJob(2, 0);
        $job = $this->normalizer->normalize($entry);

        $this->assertSame('Contract, perm possible', $job['job_type']);
        $this->assertArrayNotHasKey('employment_type', $job);
        $this->assertTrue($this->normalizer->hasUnrecognizedJobType($entry));
    }

    public function test_a_job_type_is_recognized_ignoring_case_and_whitespace(): void
    {
        $entry = [...$this->searchJob(1, 0), 'jobType' => '  perm '];
        $job = $this->normalizer->normalize($entry);

        $this->assertSame('Full Time', $job['employment_type']);
        $this->assertFalse($this->normalizer->hasUnrecognizedJobType($entry));
    }

    public function test_a_missing_job_type_is_not_unrecognized(): void
    {
        $entry = $this->searchJob(1, 0);
        unset($entry['jobType']);

        $this->assertFalse($this->normalizer->hasUnrecognizedJobType($entry));
        $this->assertArrayNotHasKey('job_type', $this->normalizer->normalize($entry));
    }

    public function test_missing_fields_are_omitted_rather_than_guessed(): void
    {
        $job = $this->normalizer->normalize(['requisitionId' => 'abc', 'jobTitle' => 'Developer']);

        $this->assertSame(['job_id', 'title', 'company', 'job_url'], array_keys($job));
    }

    public function test_a_description_with_known_headings_is_split_into_sections(): void
    {
        $job = $this->normalizer->normalize($this->searchJob(1, 1), $this->fixture('details-with-headings.json'));

        $this->assertSame(
            "10+ years of hands-on Python development experience\nFamiliarity with AI driven software development practices (GitHub Copilot)\nBachelors degree",
            $job['required_skills'],
        );
        $this->assertSame(
            "Experience with React, Vue.js, .NET Core, SQL Server, or PostgreSQL.\nAzure or AWS cloud experience.",
            $job['preferred_skills'],
        );
        $this->assertSame(
            "Seeking an experienced Full Stack Engineer to join its Product & Technology organization.\n\nThe role will focus heavily on Python development.",
            $job['job_description'],
        );
    }

    public function test_a_description_without_known_headings_is_returned_whole(): void
    {
        $job = $this->normalizer->normalize($this->searchJob(2, 0), $this->fixture('details-without-headings.json'));

        $this->assertSame(
            "A client in Philadelphia is looking for a .NET developer.\n\nYou will build internal tools & APIs.",
            $job['job_description'],
        );
        $this->assertArrayNotHasKey('required_skills', $job);
        $this->assertArrayNotHasKey('preferred_skills', $job);
    }

    public function test_headings_padded_with_non_breaking_spaces_and_block_tags_still_split(): void
    {
        $sections = $this->normalizer->descriptionSections(
            '<p>Required Skills &amp; Experience&nbsp;</p><ul><li>Vue</li><li>TypeScript</li></ul>'
            .'<p>Job Description</p><p>Build the front end.</p>'
        );

        $this->assertSame("Vue\nTypeScript", $sections['required_skills']);
        $this->assertSame('Build the front end.', $sections['job_description']);
    }

    public function test_a_job_in_a_us_state_is_a_us_job(): void
    {
        $this->assertTrue($this->normalizer->isUsJob($this->searchJob(1, 0)));
        $this->assertTrue($this->normalizer->isUsJob($this->located('Washington', 'DC')));
        $this->assertTrue($this->normalizer->isUsJob($this->located('San Juan', 'PR')));
    }

    public function test_a_job_outside_the_us_is_not_a_us_job(): void
    {
        $this->assertFalse($this->normalizer->isUsJob($this->located('Bengaluru', 'KA')));
        $this->assertFalse($this->normalizer->isUsJob($this->located('Toronto', 'ON')));
        $this->assertFalse($this->normalizer->isUsJob($this->located('São Paulo', 'São Paulo')));
    }

    public function test_an_intl_title_at_a_us_address_is_not_a_us_job(): void
    {
        $entry = [...$this->located('Atlanta', 'GA'), 'jobTitle' => 'Sr. Frontend Software Engineer - INTL INDIA'];

        $this->assertFalse($this->normalizer->isUsJob($entry));
        $this->assertFalse($this->normalizer->isUsJob([...$entry, 'jobTitle' => 'intl LATAM - Business Analyst']));
        $this->assertTrue($this->normalizer->isUsJob([...$entry, 'jobTitle' => 'International Trade Analyst']));
    }

    public function test_a_job_without_a_listed_state_is_not_a_us_job(): void
    {
        $entry = $this->searchJob(1, 0);
        unset($entry['workAddress']['administrativeArea']);

        $this->assertFalse($this->normalizer->isUsJob($entry));
        $this->assertFalse($this->normalizer->isUsJob(['jobTitle' => 'Developer']));
    }

    public function test_the_state_code_is_matched_ignoring_case_and_whitespace(): void
    {
        $this->assertTrue($this->normalizer->isUsJob($this->located('Philadelphia', ' pa ')));
    }

    public function test_the_disclaimer_is_never_returned(): void
    {
        $job = $this->normalizer->normalize($this->searchJob(1, 1), $this->fixture('details-with-headings.json'));

        $this->assertStringNotContainsString('inclusive environments', json_encode($job, JSON_THROW_ON_ERROR));
    }

    public function test_a_details_error_is_recorded_without_description_fields(): void
    {
        $job = $this->normalizer->normalize($this->searchJob(1, 1), null, [], 'HTTP 404');

        $this->assertSame('HTTP 404', $job['details_error']);
        $this->assertArrayNotHasKey('job_description', $job);
    }

    /**
     * @return array<string, mixed>
     */
    private function located(string $locality, string $state): array
    {
        return [...$this->searchJob(1, 0), 'workAddress' => ['locality' => $locality, 'administrativeArea' => $state]];
    }

    /**
     * @return array<string, mixed>
     */
    private function searchJob(int $page, int $index): array
    {
        return $this->fixture("search-page-{$page}.json")['jobs'][$index];
    }

    /**
     * @return array<string, mixed>
     */
    private function fixture(string $name): array
    {
        return json_decode(
            (string) file_get_contents(dirname(__DIR__, 3).'/Fixtures/insight-global/'.$name),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }
}
