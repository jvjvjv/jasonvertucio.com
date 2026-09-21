<?php

namespace Tests\Unit\Services\Resume;

use App\Services\Resume\SignatureImageService;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SignatureImageServiceTest extends TestCase
{
    protected string $sourcePath;

    protected string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sourcePath = dirname(__DIR__, 4).'/resources/resume/signature.png';

        if (! file_exists($this->sourcePath)) {
            $this->markTestSkipped('Signature source not found: '.$this->sourcePath);
        }

        $this->tempDir = sys_get_temp_dir().'/signature-service-test-'.uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            array_map('unlink', glob($this->tempDir.'/*') ?: []);
            rmdir($this->tempDir);
        }

        parent::tearDown();
    }

    public function test_visible_strokes_are_recolored_to_brand_blue(): void
    {
        $result = (new SignatureImageService($this->sourcePath))->build();

        $this->assertNotNull($result);

        $image = imagecreatefromstring($result['bytes']);
        $this->assertNotFalse($image);

        $strokePixel = $this->findPixel($image, fn (int $alpha): bool => $alpha === 0);
        $this->assertNotNull($strokePixel, 'Signature should contain at least one fully opaque pixel');

        [$x, $y] = $strokePixel;
        $color = imagecolorat($image, $x, $y);

        $this->assertSame(0x1B, ($color >> 16) & 0xFF, 'Red channel should be the brand blue');
        $this->assertSame(0x58, ($color >> 8) & 0xFF, 'Green channel should be the brand blue');
        $this->assertSame(0x7C, $color & 0xFF, 'Blue channel should be the brand blue');

        imagedestroy($image);
    }

    public function test_source_is_opaque_so_alpha_must_be_derived(): void
    {
        $source = imagecreatefrompng($this->sourcePath);
        imagealphablending($source, false);

        $transparent = $this->findPixel($source, fn (int $alpha): bool => $alpha > 0);
        imagedestroy($source);

        $this->assertNull(
            $transparent,
            'Precondition: the committed source carries no transparency, so it must be keyed off luminance'
        );
    }

    public function test_every_visible_pixel_is_brand_blue(): void
    {
        $result = (new SignatureImageService($this->sourcePath))->build();

        $image = imagecreatefromstring($result['bytes']);
        imagealphablending($image, false);

        $offBrand = $this->findPixelWhere(
            $image,
            function (int $color): bool {
                $alpha = ($color >> 24) & 0x7F;

                return $alpha < 127 && ($color & 0xFFFFFF) !== 0x1B587C;
            }
        );

        $this->assertNull($offBrand, 'No visible pixel may keep a color other than the brand blue');

        imagedestroy($image);
    }

    public function test_white_background_is_keyed_out(): void
    {
        $result = (new SignatureImageService($this->sourcePath))->build();

        $image = imagecreatefromstring($result['bytes']);
        imagealphablending($image, false);

        // (0,0) is background in the committed signature.
        $this->assertSame(
            127,
            (imagecolorat($image, 0, 0) >> 24) & 0x7F,
            'The white background must be fully transparent'
        );

        $this->assertNotNull(
            $this->findPixel($image, fn (int $alpha): bool => $alpha === 0),
            'Dark strokes must be fully opaque'
        );

        imagedestroy($image);
    }

    public function test_mid_luminance_pixels_become_partially_transparent(): void
    {
        $source = imagecreatefrompng($this->sourcePath);
        imagealphablending($source, false);

        $edge = $this->findPixelWhere($source, function (int $color): bool {
            $luminance = (0.299 * (($color >> 16) & 0xFF))
                + (0.587 * (($color >> 8) & 0xFF))
                + (0.114 * ($color & 0xFF));

            return $luminance > 60 && $luminance < 200;
        });
        imagedestroy($source);

        $this->assertNotNull($edge, 'Precondition: the source has anti-aliased mid-luminance pixels');

        $result = (new SignatureImageService($this->sourcePath))->build();
        $image = imagecreatefromstring($result['bytes']);
        imagealphablending($image, false);

        $alpha = (imagecolorat($image, $edge[0], $edge[1]) >> 24) & 0x7F;

        $this->assertGreaterThan(0, $alpha, 'Anti-aliased pixels must not be fully opaque');
        $this->assertLessThan(127, $alpha, 'Anti-aliased pixels must not be fully transparent');

        imagedestroy($image);
    }

    public function test_existing_source_transparency_is_not_made_opaque(): void
    {
        $rgbaPath = $this->tempDir.'/already-transparent.png';

        $source = imagecreatetruecolor(4, 1);
        imagealphablending($source, false);
        imagesavealpha($source, true);
        // A fully transparent black pixel: dark enough that a naive luminance
        // key would turn it solid.
        imagesetpixel($source, 0, 0, imagecolorallocatealpha($source, 0, 0, 0, 127));
        imagesetpixel($source, 1, 0, imagecolorallocatealpha($source, 0, 0, 0, 0));
        imagesetpixel($source, 2, 0, imagecolorallocatealpha($source, 255, 255, 255, 0));
        imagesetpixel($source, 3, 0, imagecolorallocatealpha($source, 0, 0, 0, 64));
        imagepng($source, $rgbaPath);
        imagedestroy($source);

        $result = (new SignatureImageService($rgbaPath))->build();
        $image = imagecreatefromstring($result['bytes']);
        imagealphablending($image, false);

        $this->assertSame(127, (imagecolorat($image, 0, 0) >> 24) & 0x7F, 'Transparent source pixel must stay transparent');
        $this->assertSame(0, (imagecolorat($image, 1, 0) >> 24) & 0x7F, 'Opaque dark pixel stays opaque');
        $this->assertSame(127, (imagecolorat($image, 2, 0) >> 24) & 0x7F, 'Opaque white pixel is keyed out');
        $this->assertSame(64, (imagecolorat($image, 3, 0) >> 24) & 0x7F, 'Partially transparent dark pixel keeps its alpha');

        imagedestroy($image);
    }

    public function test_height_is_two_inches_and_width_is_proportional(): void
    {
        $result = (new SignatureImageService($this->sourcePath))->build();

        $this->assertSame(1828800, $result['cy'], 'Signature must be exactly 2 inches tall');

        $expectedWidth = (int) round(1828800 * $result['width'] / $result['height']);
        $this->assertSame($expectedWidth, $result['cx']);

        // The committed 199x415 source works out to roughly 0.96 inches wide.
        $this->assertGreaterThan(850000, $result['cx']);
        $this->assertLessThan(900000, $result['cx']);
    }

    public function test_square_source_yields_equal_width_and_height(): void
    {
        $squarePath = $this->tempDir.'/square.png';
        $square = imagecreatetruecolor(64, 64);
        imagesavealpha($square, true);
        imagefill($square, 0, 0, imagecolorallocatealpha($square, 255, 0, 0, 0));
        imagepng($square, $squarePath);
        imagedestroy($square);

        $result = (new SignatureImageService($squarePath))->build();

        $this->assertSame($result['cy'], $result['cx'], 'A square source should render square');
        $this->assertSame(1828800, $result['cy']);
    }

    public function test_missing_source_returns_null_and_logs_a_warning(): void
    {
        Log::spy();

        $missingPath = $this->tempDir.'/does-not-exist.png';
        $result = (new SignatureImageService($missingPath))->build();

        $this->assertNull($result);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message, array $context = []): bool => str_contains($message, 'not found')
                && ($context['path'] ?? null) === $missingPath);
    }

    public function test_unreadable_source_returns_null_and_logs_a_warning(): void
    {
        Log::spy();

        $notAPng = $this->tempDir.'/not-a-png.png';
        file_put_contents($notAPng, 'this is not a PNG file');

        $result = (new SignatureImageService($notAPng))->build();

        $this->assertNull($result);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message, array $context = []): bool => str_contains($message, 'could not be read')
                && ($context['path'] ?? null) === $notAPng);
    }

    /**
     * Find the first pixel whose alpha satisfies the predicate.
     *
     * @param  callable(int): bool  $matches
     * @return array{0: int, 1: int}|null
     */
    protected function findPixel(\GdImage $image, callable $matches): ?array
    {
        return $this->findPixelWhere(
            $image,
            fn (int $color): bool => $matches(($color >> 24) & 0x7F)
        );
    }

    /**
     * Find the first pixel whose packed ARGB value satisfies the predicate.
     *
     * @param  callable(int): bool  $matches
     * @return array{0: int, 1: int}|null
     */
    protected function findPixelWhere(\GdImage $image, callable $matches): ?array
    {
        $width = imagesx($image);
        $height = imagesy($image);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if ($matches(imagecolorat($image, $x, $y))) {
                    return [$x, $y];
                }
            }
        }

        return null;
    }
}
