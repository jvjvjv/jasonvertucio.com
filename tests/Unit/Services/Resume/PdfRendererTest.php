<?php

namespace Tests\Unit\Services\Resume;

use App\Services\Resume\PdfRenderer;
use Tests\TestCase;

class PdfRendererTest extends TestCase
{
    protected string $outputPath;

    /** @var array<int, string> */
    protected array $scratchFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->outputPath = sys_get_temp_dir().'/pdf-renderer-test-'.uniqid().'.pdf';
    }

    protected function tearDown(): void
    {
        foreach (array_merge($this->scratchFiles, [$this->outputPath]) as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    public function test_successful_render_returns_the_output_path(): void
    {
        if (! $this->weasyprintAvailable()) {
            $this->markTestSkipped('weasyprint binary not available in this environment.');
        }

        $result = (new PdfRenderer)->render('<html><body>Hello</body></html>', $this->outputPath);

        $this->assertTrue($result['success'], $result['error'] ?? '');
        $this->assertSame($this->outputPath, $result['path']);
        $this->assertFileExists($this->outputPath);
        $this->assertGreaterThan(0, $result['size']);
    }

    public function test_a_non_zero_exit_returns_a_failure_carrying_process_output(): void
    {
        config(['resume.weasyprint' => $this->fakeBinary(
            "#!/bin/sh\necho 'boom: bad markup' >&2\nexit 2\n"
        )]);

        $result = (new PdfRenderer)->render('<html></html>', $this->outputPath);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('boom: bad markup', $result['error']);
        $this->assertFileDoesNotExist($this->outputPath);
    }

    public function test_a_timeout_returns_a_failure_and_leaves_no_file(): void
    {
        config(['resume.weasyprint' => $this->fakeBinary("#!/bin/sh\nsleep 5\n")]);
        config(['resume.weasyprint_timeout' => 1]);

        $result = (new PdfRenderer)->render('<html></html>', $this->outputPath);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('timed out', $result['error']);
        $this->assertFileDoesNotExist($this->outputPath);
    }

    public function test_a_missing_binary_is_reported_as_unavailable(): void
    {
        config(['resume.weasyprint' => '/no/such/binary/weasyprint-'.uniqid()]);

        $result = (new PdfRenderer)->render('<html></html>', $this->outputPath);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not installed or cannot be executed', $result['error']);
        $this->assertFileDoesNotExist($this->outputPath);
    }

    public function test_a_non_executable_binary_is_reported_as_unavailable(): void
    {
        $path = sys_get_temp_dir().'/weasyprint-not-executable-'.uniqid();
        file_put_contents($path, "#!/bin/sh\necho hi\n");
        chmod($path, 0644);
        $this->scratchFiles[] = $path;

        config(['resume.weasyprint' => $path]);

        $result = (new PdfRenderer)->render('<html></html>', $this->outputPath);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not installed or cannot be executed', $result['error']);
        $this->assertFileDoesNotExist($this->outputPath);
    }

    protected function fakeBinary(string $script): string
    {
        $path = sys_get_temp_dir().'/fake-weasyprint-'.uniqid();
        file_put_contents($path, $script);
        chmod($path, 0755);
        $this->scratchFiles[] = $path;

        return $path;
    }

    protected function weasyprintAvailable(): bool
    {
        $binary = (string) config('resume.weasyprint');
        exec('command -v '.escapeshellarg($binary).' 2>/dev/null', $output, $exitCode);

        return $exitCode === 0;
    }
}
