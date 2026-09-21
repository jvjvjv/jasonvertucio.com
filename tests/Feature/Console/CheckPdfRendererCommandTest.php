<?php

namespace Tests\Feature\Console;

use Tests\TestCase;

class CheckPdfRendererCommandTest extends TestCase
{
    public function test_it_exits_successfully_when_the_renderer_is_available(): void
    {
        exec('command -v '.escapeshellarg((string) config('resume.weasyprint')).' 2>/dev/null', $output, $exitCode);

        if ($exitCode !== 0) {
            $this->markTestSkipped('weasyprint binary not available in this environment.');
        }

        $this->artisan('resume:check-pdf-renderer')
            ->expectsOutputToContain('Render succeeded')
            ->assertExitCode(0);
    }

    public function test_it_exits_with_a_clear_message_when_the_binary_is_missing(): void
    {
        config(['resume.weasyprint' => '/no/such/binary/weasyprint-'.uniqid()]);

        $this->artisan('resume:check-pdf-renderer')
            ->expectsOutputToContain('not installed or cannot be executed')
            ->assertExitCode(1);
    }
}
