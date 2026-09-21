<?php

namespace App\Services\Resume;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Exception\RuntimeException as ProcessRuntimeException;
use Symfony\Component\Process\Process;

/**
 * Renders a PDF from composed HTML by invoking WeasyPrint as a subprocess.
 *
 * Runs the binary via Symfony's `Process` with an argument array — no shell,
 * unlike the `exec('libreoffice ...')` call this replaces — so a filename or
 * HTML content can never be interpreted as shell syntax, and so a timeout can
 * actually be enforced.
 */
class PdfRenderer
{
    /**
     * Render HTML to a PDF at the given output path.
     *
     * Renders to a temporary file first and moves it into place only on
     * success, so a failed or abandoned render never leaves a partial file
     * where the caller expects the finished PDF, and never disturbs a
     * previously generated PDF already there.
     *
     * @return array{success: bool, path?: string, size?: int, error?: string}
     */
    public function render(string $html, string $outputPath): array
    {
        $binary = (string) config('resume.weasyprint');

        $outputDir = dirname($outputPath);
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $htmlPath = tempnam(sys_get_temp_dir(), 'resume-html-').'.html';
        $pdfTempPath = tempnam(sys_get_temp_dir(), 'resume-pdf-').'.pdf';

        try {
            file_put_contents($htmlPath, $html);

            $process = new Process([$binary, '-q', $htmlPath, $pdfTempPath]);
            $process->setTimeout((int) config('resume.weasyprint_timeout'));

            try {
                $process->run();
            } catch (ProcessTimedOutException $e) {
                return $this->failure('PDF rendering timed out after '.$e->getExceededTimeout().'s.');
            } catch (ProcessRuntimeException $e) {
                // A signaled process (e.g. a segfault) throws rather than
                // merely returning a non-zero exit code.
                Log::error('PDF rendering process failed abnormally', [
                    'binary' => $binary,
                    'message' => $e->getMessage(),
                ]);

                return $this->failure('WeasyPrint rendering failed: '.$e->getMessage());
            }

            if (! $process->isSuccessful()) {
                return $this->rendererFailure($binary, $process);
            }

            if (! file_exists($pdfTempPath) || filesize($pdfTempPath) === 0) {
                return $this->failure('WeasyPrint reported success but produced no output.');
            }

            if (! rename($pdfTempPath, $outputPath)) {
                return $this->failure("Could not move rendered PDF into place: {$outputPath}");
            }

            return [
                'success' => true,
                'path' => $outputPath,
                'size' => filesize($outputPath),
            ];
        } finally {
            @unlink($htmlPath);
            @unlink($pdfTempPath);
        }
    }

    /**
     * Distinguish a missing or non-executable binary from a rendering
     * failure the binary itself reported.
     *
     * @return array{success: false, error: string}
     */
    protected function rendererFailure(string $binary, Process $process): array
    {
        $exitCode = $process->getExitCode();
        $errorOutput = trim($process->getErrorOutput());

        // Symfony reports "command not found" as exit code 127 and a
        // present-but-non-executable file as 126 (both via the shell it
        // falls back to when it cannot exec directly) — but a bare exec()
        // failure (no fork at all) leaves exit code null. Both are the
        // renderer being unavailable, not a rendering problem.
        if ($exitCode === null
            || in_array($exitCode, [126, 127], true)
            || str_contains($errorOutput, 'No such file or directory')
            || str_contains($errorOutput, 'Permission denied')) {
            return $this->failure("PDF renderer is not installed or cannot be executed: {$binary}");
        }

        Log::error('PDF rendering failed', [
            'binary' => $binary,
            'exitCode' => $exitCode,
            'error' => $errorOutput,
        ]);

        return $this->failure('WeasyPrint rendering failed: '.($errorOutput !== '' ? $errorOutput : 'exit code '.$exitCode));
    }

    /**
     * @return array{success: false, error: string}
     */
    protected function failure(string $error): array
    {
        return [
            'success' => false,
            'error' => $error,
        ];
    }
}
