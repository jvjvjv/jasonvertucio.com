<?php

namespace App\Console\Commands;

use App\Services\Resume\FontEmbedder;
use Illuminate\Console\Command;
use RuntimeException;

class EmbedResumeFontsCommand extends Command
{
    protected $signature = 'resume:embed-fonts
        {--check : Report what is embedded without modifying the template}
        {--template= : Template to operate on (defaults to config resume.template)}
        {--fonts= : Directory of static faces (defaults to config resume.fonts)}';

    protected $description = 'Embed the repo\'s static font faces into the resume template, replacing whatever Word last embedded';

    public function handle(FontEmbedder $embedder): int
    {
        $template = (string) ($this->option('template') ?: config('resume.template'));
        $fontDir = (string) ($this->option('fonts') ?: config('resume.fonts'));
        $check = (bool) $this->option('check');

        if (! is_file($template)) {
            $this->error("Template not found: {$template}");

            return self::FAILURE;
        }

        try {
            $faces = $embedder->loadFaces($fontDir);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($faces === []) {
            $this->error("No usable font faces in {$fontDir}");

            return self::FAILURE;
        }

        $this->line('Faces available in '.$fontDir.':');
        foreach ($faces as $face) {
            $this->line(sprintf(
                '  %-34s %s%s',
                $face['family'].' '.$face['subfamily'],
                basename($face['path']),
                $face['variable'] ? '  <-- VARIABLE FONT, do not use' : ''
            ));
        }
        $this->newLine();

        if ($variable = array_filter($faces, fn (array $f): bool => $f['variable'])) {
            $this->error('Refusing to embed: '.count($variable).' supplied face(s) are variable fonts.');
            $this->line('A variable face collapses every weight onto one instance and renders Thin.');
            $this->line('Download the family\'s "static" folder and use those files instead.');

            return self::FAILURE;
        }

        try {
            $report = $embedder->embed($template, $faces, $check);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Family', 'Slot', 'Status', 'Face'],
            array_map(fn (array $r): array => [
                $r['family'],
                str_replace('embed', '', $r['slot']),
                $r['status'],
                $r['detail'],
            ], $report)
        );

        $stillVariable = array_filter($report, fn (array $r): bool => $r['status'] === 'VARIABLE');

        if ($stillVariable !== []) {
            $this->warn(count($stillVariable).' slot(s) still hold a variable font and no replacement was supplied.');
            $this->line('Add the matching static face to '.$fontDir.' and re-run.');

            return self::FAILURE;
        }

        $embedded = count(array_filter($report, fn (array $r): bool => $r['status'] === 'embedded'));

        if ($check) {
            $this->info("Check only — {$embedded} slot(s) would be replaced. Nothing was written.");

            return self::SUCCESS;
        }

        $this->info("Embedded {$embedded} face(s) into ".basename($template));

        return self::SUCCESS;
    }
}
