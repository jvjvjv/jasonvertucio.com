<?php

namespace Tests\Feature;

use App\Events\MediaPlaybackMilestoneReached;
use App\Listeners\LogSocialPostStub;
use App\Models\LocalMedia;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class LogSocialPostStubTest extends TestCase
{
    use DatabaseTransactions;

    public function test_listener_logs_and_makes_no_http_calls(): void
    {
        Http::fake();
        Log::shouldReceive('info')
            ->once()
            ->with('social post stub: would post Finished Movie');

        $media = LocalMedia::factory()->create(['title' => 'Finished Movie']);
        $event = new MediaPlaybackMilestoneReached($media, 9500, 10000, CarbonImmutable::now());

        (new LogSocialPostStub)->handle($event);

        Http::assertNothingSent();
    }
}
