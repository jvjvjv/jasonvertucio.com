<?php

namespace App\Console\Commands;

use App\Services\Resume\PdfRenderer;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Smoke-tests the WeasyPrint PDF renderer: reports the configured binary's
 * resolved path and version, then renders a small fixture document through
 * the same `PdfRenderer` the document services use.
 *
 * A deploy step, so a packaging or SELinux problem is caught here rather than
 * surfacing later as "approved, but document generation failed".
 */
class CheckPdfRendererCommand extends Command
{
    protected $signature = 'resume:check-pdf-renderer';

    protected $description = 'Verify the configured WeasyPrint binary is installed, executable, and can render a PDF';

    public function handle(PdfRenderer $renderer): int
    {
        $binary = (string) config('resume.weasyprint');

        $resolved = $this->resolveBinary($binary);

        if ($resolved === null) {
            $this->error("PDF renderer is not installed or cannot be executed: {$binary}");

            return self::FAILURE;
        }

        $this->line("Binary: {$resolved}");

        $version = $this->binaryVersion($binary);
        $this->line('Version: '.($version ?? 'unknown'));

        $outputPath = tempnam(sys_get_temp_dir(), 'resume-pdf-check-').'.pdf';

        try {
            $result = $renderer->render(
                '<!DOCTYPE html><html><body><p>resume:check-pdf-renderer fixture</p></body></html>',
                $outputPath,
            );
        } finally {
            @unlink($outputPath);
        }

        if (! $result['success']) {
            $this->error('Render failed: '.$result['error']);

            return self::FAILURE;
        }

        $this->info('Render succeeded ('.$result['size'].' bytes).');

        return self::SUCCESS;
    }

    protected function resolveBinary(string $binary): ?string
    {
        if (str_contains($binary, '/')) {
            return is_executable($binary) ? $binary : null;
        }

        // 'command -v' is a shell builtin, not an executable — Process's
        // array form runs no shell, so 'which' (a real binary, including on
        // busybox) is what actually resolves PATH here.
        $process = new Process(['which', $binary]);
        $process->run();

        $path = trim($process->getOutput());

        return $process->isSuccessful() && $path !== '' ? $path : null;
    }

    protected function binaryVersion(string $binary): ?string
    {
        $process = new Process([$binary, '--version']);
        $process->run();

        $output = trim($process->getOutput().$process->getErrorOutput());

        return $process->isSuccessful() && $output !== '' ? $output : null;
    }
}
