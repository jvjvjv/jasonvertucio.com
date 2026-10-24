<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PaperLiRemovalTest extends TestCase
{
    public function test_paper_harvest_command_is_not_registered(): void
    {
        $this->assertArrayNotHasKey('paper:harvest', Artisan::all());
    }

    public function test_paper_route_does_not_exist(): void
    {
        $this->get('/paper')->assertNotFound();
    }
}
