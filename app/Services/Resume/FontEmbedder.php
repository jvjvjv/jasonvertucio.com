<?php

namespace App\Services\Resume;

use RuntimeException;
use ZipArchive;

/**
 * Writes static TTF faces into a DOCX template's embedded font parts.
 *
 * Word re-embeds fonts from whatever is installed on the machine that saved
 * the file. When a family is installed as a *variable* font, Word puts the
 * same physical file into every weight slot, and LibreOffice — which does not
 * instantiate the weight axis — renders the whole document at the axis
 * default. That is how every face silently became Thin. Embedding from faces
 * committed to the repo makes the result reproducible instead.
 */
class FontEmbedder
{
    /**
     * Word's four embeddable slots, mapped to the subfamily each one needs.
     */
    protected const SLOTS = [
        'embedRegular' => 'Regular',
        'embedBold' => 'Bold',
        'embedItalic' => 'Italic',
        'embedBoldItalic' => 'Bold Italic',
    ];

    /**
     * Suffixes Word leaves on a family name when a font is installed
     * per-weight ("Josefin Sans Bold"), which is itself a symptom of a
     * variable or split install. The suffix folds back into the slot.
     *
     * Longest first, so "Bold Italic" wins over "Bold".
     */
    protected const FAMILY_SUFFIXES = ['Bold Italic', 'BoldItalic', 'Regular', 'Bold', 'Italic'];

    /**
     * Load every usable face from a directory, keyed by "family|subfamily".
     *
     * Faces are identified by their own `name` table rather than by filename,
     * so a misnamed file cannot land in the wrong slot.
     *
     * @return array<string, array{path: string, family: string, subfamily: string, variable: bool}>
     */
    public function loadFaces(string $directory): array
    {
        if (! is_dir($directory)) {
            throw new RuntimeException("Font directory not found: {$directory}");
        }

        $faces = [];

        foreach ($this->findFontFiles($directory) as $path) {
            $bytes = (string) file_get_contents($path);
            $names = $this->readNameTable($bytes);

            $family = $names[1] ?? null;
            $subfamily = $names[2] ?? 'Regular';

            if ($family === null) {
                continue;
            }

            $faces[$this->faceKey($family, $subfamily)] = [
                'path' => $path,
                'family' => $family,
                'subfamily' => $subfamily,
                'variable' => str_contains($bytes, 'fvar'),
            ];
        }

        return $faces;
    }

    /**
     * Font files in a directory, matched case-insensitively by extension.
     *
     * `GLOB_BRACE` is a libc extension glibc provides and musl (this
     * project's Alpine images) does not, so the constant is undefined there
     * rather than merely unsupported — a brace pattern would fatal, not just
     * fail to match. Matching each extension with its own glob() call is
     * portable to both.
     *
     * @return array<int, string>
     */
    protected function findFontFiles(string $directory): array
    {
        $directory = rtrim($directory, '/');
        $paths = [];

        foreach (['ttf', 'TTF', 'otf', 'OTF'] as $extension) {
            $paths = array_merge($paths, glob($directory.'/*.'.$extension) ?: []);
        }

        return array_values(array_unique($paths));
    }

    /**
     * Resolve which face belongs in a given font-table slot.
     *
     * @param  array<string, array{path: string, family: string, subfamily: string, variable: bool}>  $faces
     * @return array{path: string, family: string, subfamily: string, variable: bool}|null
     */
    public function resolveFace(array $faces, string $declaredFamily, string $slot): ?array
    {
        $wanted = self::SLOTS[$slot] ?? 'Regular';
        [$family, $impliedStyle] = $this->splitFamilySuffix($declaredFamily);

        // "Josefin Sans Bold" + embedBold is still just Josefin Sans Bold.
        $candidates = array_unique([
            $this->faceKey($declaredFamily, $wanted),
            $this->faceKey($family, $wanted),
            $impliedStyle !== null ? $this->faceKey($family, $impliedStyle) : null,
        ]);

        foreach (array_filter($candidates) as $key) {
            if (isset($faces[$key])) {
                return $faces[$key];
            }
        }

        return null;
    }

    /**
     * Rewrite a template's font parts in place from the given faces.
     *
     * A family with no matching face keeps whatever it already has, so
     * supplying two families does not strip the rest.
     *
     * @param  array<string, array{path: string, family: string, subfamily: string, variable: bool}>  $faces
     * @return array<int, array{family: string, slot: string, status: string, detail: string}>
     */
    public function embed(string $templatePath, array $faces, bool $dryRun = false): array
    {
        $zip = new ZipArchive;

        if ($zip->open($templatePath) !== true) {
            throw new RuntimeException("Cannot open template: {$templatePath}");
        }

        $fontTable = $zip->getFromName('word/fontTable.xml');
        $rels = $zip->getFromName('word/_rels/fontTable.xml.rels');

        if ($fontTable === false) {
            $zip->close();

            throw new RuntimeException('Template has no word/fontTable.xml — nothing is embedded in it.');
        }

        $relTargets = $this->relationshipTargets((string) $rels);
        $report = [];
        $replacements = [];

        $newFontTable = preg_replace_callback(
            '/<w:font w:name="([^"]+)">(.*?)<\/w:font>/s',
            function (array $font) use ($faces, $relTargets, $zip, &$report, &$replacements): string {
                $family = $font[1];

                return '<w:font w:name="'.$family.'">'.preg_replace_callback(
                    '/<w:embed(Regular|BoldItalic|Bold|Italic)([^>]*)\/>/',
                    function (array $embed) use ($family, $faces, $relTargets, $zip, &$report, &$replacements): string {
                        $slot = 'embed'.$embed[1];
                        $attrs = $embed[2];

                        preg_match('/r:id="([^"]+)"/', $attrs, $rid);
                        $target = $relTargets[$rid[1] ?? ''] ?? null;
                        $face = $this->resolveFace($faces, $family, $slot);

                        if ($target === null) {
                            $report[] = $this->line($family, $slot, 'skipped', 'no relationship target');

                            return $embed[0];
                        }

                        if ($face === null) {
                            $existing = $zip->getFromName('word/'.$target);
                            $variable = is_string($existing) && str_contains($existing, 'fvar');
                            $report[] = $this->line(
                                $family,
                                $slot,
                                $variable ? 'VARIABLE' : 'kept',
                                $variable ? 'no face supplied — still a variable font' : 'no face supplied'
                            );

                            return $embed[0];
                        }

                        $key = $this->newFontKey();
                        $replacements['word/'.$target] = $this->obfuscate(
                            (string) file_get_contents($face['path']),
                            $key
                        );

                        $report[] = $this->line(
                            $family,
                            $slot,
                            'embedded',
                            basename($face['path']).' ('.$face['family'].' '.$face['subfamily'].')'
                        );

                        return '<w:embed'.$embed[1].preg_replace(
                            '/w:fontKey="\{[^"]*\}"/',
                            'w:fontKey="'.$key.'"',
                            $attrs
                        ).'/>';
                    },
                    $font[2]
                ).'</w:font>';
            },
            $fontTable
        );

        $zip->close();

        if (! $dryRun && $replacements !== []) {
            $this->write($templatePath, (string) $newFontTable, $replacements);
        }

        return $report;
    }

    /**
     * Rewrite the archive with the new font parts and font table.
     *
     * @param  array<string, string>  $replacements
     */
    protected function write(string $templatePath, string $fontTable, array $replacements): void
    {
        $source = new ZipArchive;

        if ($source->open($templatePath) !== true) {
            throw new RuntimeException("Cannot reopen template: {$templatePath}");
        }

        $temp = $templatePath.'.embedding';
        $out = new ZipArchive;

        if ($out->open($temp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $source->close();

            throw new RuntimeException("Cannot create temporary archive: {$temp}");
        }

        for ($i = 0; $i < $source->numFiles; $i++) {
            $name = (string) $source->getNameIndex($i);

            $out->addFromString($name, match (true) {
                $name === 'word/fontTable.xml' => $fontTable,
                isset($replacements[$name]) => $replacements[$name],
                default => (string) $source->getFromIndex($i),
            });
        }

        $out->close();
        $source->close();

        if (! rename($temp, $templatePath)) {
            @unlink($temp);

            throw new RuntimeException("Could not replace template at {$templatePath}");
        }
    }

    /**
     * Apply Word's font obfuscation: the first 32 bytes are XORed with the
     * font key's 16 bytes, reversed, applied twice.
     */
    public function obfuscate(string $font, string $fontKey): string
    {
        $key = strrev((string) hex2bin(str_replace(['{', '}', '-'], '', $fontKey)));
        $bytes = $font;

        for ($i = 0; $i < 32 && $i < strlen($bytes); $i++) {
            $bytes[$i] = chr(ord($bytes[$i]) ^ ord($key[$i % 16]));
        }

        return $bytes;
    }

    /**
     * Read the TrueType `name` table, preferring the Windows/UCS-2 records.
     *
     * @return array<int, string>
     */
    public function readNameTable(string $font): array
    {
        if (strlen($font) < 12) {
            return [];
        }

        $numTables = unpack('n', substr($font, 4, 2))[1];
        $nameOffset = null;

        for ($i = 0; $i < $numTables; $i++) {
            $entry = substr($font, 12 + ($i * 16), 16);

            if (strlen($entry) < 16) {
                break;
            }

            if (substr($entry, 0, 4) === 'name') {
                $nameOffset = unpack('N', substr($entry, 8, 4))[1];
                break;
            }
        }

        if ($nameOffset === null || strlen($font) < $nameOffset + 6) {
            return [];
        }

        $count = unpack('n', substr($font, $nameOffset + 2, 2))[1];
        $storage = $nameOffset + unpack('n', substr($font, $nameOffset + 4, 2))[1];
        $names = [];

        for ($i = 0; $i < $count; $i++) {
            $record = substr($font, $nameOffset + 6 + ($i * 12), 12);

            if (strlen($record) < 12) {
                break;
            }

            $parts = unpack('nplatform/nencoding/nlanguage/nname/nlength/noffset', $record);
            $value = substr($font, $storage + $parts['offset'], $parts['length']);

            if ($value === '') {
                continue;
            }

            // Platform 3 (Windows) stores UTF-16BE; platform 1 (Mac) is ASCII.
            $decoded = $parts['platform'] === 3
                ? (string) mb_convert_encoding($value, 'UTF-8', 'UTF-16BE')
                : $value;

            // Prefer the Windows record when both exist.
            if (! isset($names[$parts['name']]) || $parts['platform'] === 3) {
                $names[$parts['name']] = trim($decoded);
            }
        }

        return $names;
    }

    /**
     * @return array<string, string>
     */
    protected function relationshipTargets(string $rels): array
    {
        preg_match_all('/Id="([^"]+)"[^>]*Target="([^"]+)"/', $rels, $matches, PREG_SET_ORDER);

        $targets = [];
        foreach ($matches as $match) {
            $targets[$match[1]] = $match[2];
        }

        return $targets;
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    protected function splitFamilySuffix(string $family): array
    {
        return self::splitPerWeightFamily($family);
    }

    /**
     * Fold a per-weight pseudo-family ("Josefin Sans Bold") back onto its
     * real family name ("Josefin Sans"), the same way `embed()` does for the
     * DOCX — the shared template's `rFonts` carries these directly for some
     * styles (Title, TitleChar), and anything reading `rFonts` literally,
     * such as `StylesheetTranslator`, must fold them the same way or its CSS
     * `font-family` will name a family no `@font-face` rule ever declares.
     *
     * @return array{0: string, 1: string|null} The real family, and the
     *  subfamily the suffix implied (null when the name carried no suffix).
     */
    public static function splitPerWeightFamily(string $family): array
    {
        foreach (self::FAMILY_SUFFIXES as $suffix) {
            if (str_ends_with($family, ' '.$suffix)) {
                $style = $suffix === 'BoldItalic' ? 'Bold Italic' : $suffix;

                return [trim(substr($family, 0, -strlen($suffix) - 1)), $style];
            }
        }

        return [$family, null];
    }

    protected function faceKey(string $family, string $subfamily): string
    {
        return strtolower($family).'|'.strtolower(str_replace(' ', '', $subfamily));
    }

    protected function newFontKey(): string
    {
        $bytes = random_bytes(16);

        return sprintf(
            '{%s-%s-%s-%s-%s}',
            strtoupper(bin2hex(substr($bytes, 0, 4))),
            strtoupper(bin2hex(substr($bytes, 4, 2))),
            strtoupper(bin2hex(substr($bytes, 6, 2))),
            strtoupper(bin2hex(substr($bytes, 8, 2))),
            strtoupper(bin2hex(substr($bytes, 10, 6))),
        );
    }

    /**
     * @return array{family: string, slot: string, status: string, detail: string}
     */
    protected function line(string $family, string $slot, string $status, string $detail): array
    {
        return ['family' => $family, 'slot' => $slot, 'status' => $status, 'detail' => $detail];
    }
}
