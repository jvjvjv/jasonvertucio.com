<?php

namespace Tests\Unit\Services\JobSearch;

use App\Services\JobSearch\InsightGlobal\RequestBudget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class RequestBudgetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-18 12:00:00');
        Sleep::fake(syncWithCarbon: true);
    }

    public function test_the_first_request_is_not_delayed_and_later_ones_are_spaced_two_seconds(): void
    {
        $budget = new RequestBudget;

        $this->assertTrue($budget->acquire());
        $this->assertTrue($budget->acquire());
        $this->assertTrue($budget->acquire());

        Sleep::assertSleptTimes(2);
        Sleep::assertSequence([Sleep::for(2)->seconds(), Sleep::for(2)->seconds()]);
    }

    public function test_the_request_after_the_cap_is_refused(): void
    {
        $budget = new RequestBudget;

        for ($i = 1; $i <= RequestBudget::MAX_REQUESTS; $i++) {
            $this->assertTrue($budget->acquire(), "request {$i} should be allowed");
        }

        $this->assertFalse($budget->acquire());
        $this->assertSame(RequestBudget::MAX_REQUESTS, $budget->sent());
    }

    public function test_a_request_is_refused_once_it_could_not_finish_inside_the_time_budget(): void
    {
        $budget = new RequestBudget;

        $this->assertTrue($budget->acquire());

        Carbon::setTestNow(Carbon::now()->addSeconds(RequestBudget::MAX_SECONDS - RequestBudget::REQUEST_TIMEOUT_SECONDS - RequestBudget::SPACING_SECONDS));
        $this->assertTrue($budget->acquire());

        Carbon::setTestNow(Carbon::now()->addSecond());
        $this->assertFalse($budget->acquire());
    }

    public function test_a_refused_request_does_not_sleep(): void
    {
        $budget = new RequestBudget(maxRequests: 1);

        $this->assertTrue($budget->acquire());
        $this->assertFalse($budget->acquire());

        Sleep::assertNeverSlept();
    }
}
