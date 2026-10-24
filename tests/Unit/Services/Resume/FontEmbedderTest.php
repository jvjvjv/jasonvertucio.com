<?php

namespace Tests\Unit\Services\Resume;

use App\Services\Resume\FontEmbedder;
use PHPUnit\Framework\TestCase;

class FontEmbedderTest extends TestCase
{
    protected FontEmbedder $embedder;

    protected string $fontDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->embedder = new FontEmbedder;
        $this->fontDir = dirname(__DIR__, 4).'/resources/resume/assets/fonts';

        if (! is_dir($this->fontDir)) {
            $this->markTestSkipped('Font directory not found: '.$this->fontDir);
        }
    }

    public function test_faces_are_identified_by_their_name_table_not_their_filename(): void
    {
        $faces = $this->embedder->loadFaces($this->fontDir);

        $this->assertNotEmpty($faces);

        foreach ($faces as $key => $face) {
            $this->assertSame(
                strtolower($face['family']).'|'.strtolower(str_replace(' ', '', $face['subfamily'])),
                $key
            );
            $this->assertNotSame('', $face['family']);
        }
    }

    public function test_repo_faces_are_static_not_variable(): void
    {
        foreach ($this->embedder->loadFaces($this->fontDir) as $face) {
            $this->assertFalse(
                $face['variable'],
                $face['path'].' is a variable font; it would render every weight at the axis default'
            );
        }
    }

    public function test_obfuscation_round_trips(): void
    {
        $key = '{0D7FC6B9-00B7-4C79-8F7D-9CE6979FABFB}';
        $font = random_bytes(200);

        $obfuscated = $this->embedder->obfuscate($font, $key);

        $this->assertNotSame($font, $obfuscated, 'The first 32 bytes must be scrambled');
        $this->assertSame(strlen($font), strlen($obfuscated));
        $this->assertSame(
            $font,
            $this->embedder->obfuscate($obfuscated, $key),
            'Applying the same key again must restore the original bytes'
        );
    }

    public function test_obfuscation_leaves_bytes_past_the_header_untouched(): void
    {
        $font = random_bytes(200);

        $obfuscated = $this->embedder->obfuscate($font, '{0D7FC6B9-00B7-4C79-8F7D-9CE6979FABFB}');

        $this->assertSame(substr($font, 32), substr($obfuscated, 32));
    }

    public function test_a_per_weight_family_name_folds_into_the_slot(): void
    {
        $faces = $this->embedder->loadFaces($this->fontDir);

        $resolved = $this->embedder->resolveFace($faces, 'Josefin Sans Bold', 'embedBold');

        $this->assertNotNull($resolved, 'A "Family Weight" pseudo-family must resolve to the real face');
        $this->assertSame('Josefin Sans', $resolved['family']);
        $this->assertSame('Bold', $resolved['subfamily']);
    }

    public function test_a_slot_resolves_to_its_own_subfamily(): void
    {
        $faces = $this->embedder->loadFaces($this->fontDir);

        $regular = $this->embedder->resolveFace($faces, 'Montserrat', 'embedRegular');
        $bold = $this->embedder->resolveFace($faces, 'Montserrat', 'embedBold');

        $this->assertSame('Regular', $regular['subfamily']);
        $this->assertSame('Bold', $bold['subfamily']);
        $this->assertNotSame(
            $regular['path'],
            $bold['path'],
            'Regular and Bold must come from different files — sharing one is the variable-font bug'
        );
    }

    public function test_an_unsupplied_family_resolves_to_nothing(): void
    {
        $faces = $this->embedder->loadFaces($this->fontDir);

        $this->assertNull($this->embedder->resolveFace($faces, 'Consolas', 'embedRegular'));
    }

    public function test_name_table_reads_family_and_subfamily(): void
    {
        $path = $this->fontDir.'/Montserrat-Bold.ttf';

        if (! is_file($path)) {
            $this->markTestSkipped('Montserrat-Bold.ttf not present');
        }

        $names = $this->embedder->readNameTable((string) file_get_contents($path));

        $this->assertSame('Montserrat', $names[1]);
        $this->assertSame('Bold', $names[2]);
    }
}
