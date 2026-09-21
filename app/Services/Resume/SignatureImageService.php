<?php

namespace App\Services\Resume;

use GdImage;
use Illuminate\Support\Facades\Log;

/**
 * Prepares the handwritten signature image for embedding in a cover letter.
 *
 * The committed source is recolored to the brand blue at generation time
 * rather than being stored pre-colored, so the signature always tracks the
 * brand color rather than drifting away from it silently.
 */
class SignatureImageService
{
    /**
     * Brand blue — matches --color-primary in resources/css/resume.css.
     */
    public const BRAND_BLUE = [0x1B, 0x58, 0x7C];

    /**
     * English Metric Units per inch, the unit OOXML sizes drawings in.
     */
    public const EMU_PER_INCH = 914400;

    /**
     * The signature renders two inches tall — close to life size — with width
     * following the source's aspect ratio.
     *
     * Two inches does not fit between the closing and the typed name at normal
     * line spacing, and it does not have to: the signature is placed as a
     * floating image that overlaps both, the way a pen crosses whatever is
     * already printed on the page. See CoverLetterBodyComposer.
     */
    public const TARGET_HEIGHT_INCHES = 2;

    public const MEDIA_NAME = 'signature.png';

    protected string $sourcePath;

    public function __construct(?string $sourcePath = null)
    {
        $this->sourcePath = $sourcePath ?? resource_path('resume/signature.png');
    }

    /**
     * Build the brand-colored signature, sized for inline embedding.
     *
     * Returns null when the signature cannot be produced — a cover letter
     * without a signature is still usable, so this never throws.
     *
     * @return array{bytes: string, extension: string, contentType: string, cx: int, cy: int, width: int, height: int}|null
     */
    public function build(): ?array
    {
        $image = $this->loadSource();

        if ($image === null) {
            return null;
        }

        try {
            $width = imagesx($image);
            $height = imagesy($image);

            if ($width < 1 || $height < 1) {
                Log::warning('Signature image has no usable dimensions; skipping it.', [
                    'path' => $this->sourcePath,
                ]);

                return null;
            }

            $keyed = $this->keyToBrandBlue($image);

            try {
                $bytes = $this->encodePng($keyed);
            } finally {
                imagedestroy($keyed);
            }

            if ($bytes === null) {
                return null;
            }

            $cy = (int) round(self::TARGET_HEIGHT_INCHES * self::EMU_PER_INCH);

            return [
                'bytes' => $bytes,
                'extension' => 'png',
                'contentType' => 'image/png',
                'cx' => (int) round($cy * $width / $height),
                'cy' => $cy,
                'width' => $width,
                'height' => $height,
            ];
        } finally {
            imagedestroy($image);
        }
    }

    /**
     * Load the source PNG, logging and returning null on any failure.
     */
    protected function loadSource(): ?GdImage
    {
        if (! function_exists('imagecreatefrompng')) {
            Log::warning('GD PNG support is unavailable; cover letter signature skipped.');

            return null;
        }

        if (! file_exists($this->sourcePath)) {
            Log::warning('Signature image not found; cover letter signature skipped.', [
                'path' => $this->sourcePath,
            ]);

            return null;
        }

        $image = @imagecreatefrompng($this->sourcePath);

        if ($image === false) {
            Log::warning('Signature image could not be read as a PNG; cover letter signature skipped.', [
                'path' => $this->sourcePath,
            ]);

            return null;
        }

        return $image;
    }

    /**
     * Key the signature off its background and repaint it in the brand blue.
     *
     * The committed source is an opaque scan — dark strokes on solid white,
     * with no alpha of its own — so transparency has to be derived rather than
     * read. Luminance supplies it: white becomes invisible, dark strokes
     * become solid, and the near-white pixels along each stroke become
     * proportionally faint, which is what keeps the edges smooth. Any alpha a
     * future source does carry is honored too, so replacing the file with a
     * real RGBA signature does not make its transparent area opaque.
     */
    protected function keyToBrandBlue(GdImage $image): GdImage
    {
        // On a palette image imagecolorat() returns an index rather than a
        // packed ARGB value, so truecolor makes the read well-defined.
        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        imagealphablending($image, false);

        $width = imagesx($image);
        $height = imagesy($image);

        $keyed = imagecreatetruecolor($width, $height);
        imagealphablending($keyed, false);
        imagesavealpha($keyed, true);

        [$red, $green, $blue] = self::BRAND_BLUE;

        // GD alpha runs 0 (opaque) to 127 (fully transparent).
        $fullyTransparent = 127;

        imagefill($keyed, 0, 0, imagecolorallocatealpha($keyed, $red, $green, $blue, $fullyTransparent));

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $color = imagecolorat($image, $x, $y);
                $sourceAlpha = ($color >> 24) & 0x7F;

                if ($sourceAlpha === $fullyTransparent) {
                    continue;
                }

                $luminance = (0.299 * (($color >> 16) & 0xFF))
                    + (0.587 * (($color >> 8) & 0xFF))
                    + (0.114 * ($color & 0xFF));

                // The lighter the pixel, the more transparent it becomes.
                $keyedAlpha = (int) round($fullyTransparent * ($luminance / 255));

                // Never make a pixel more opaque than the source already was.
                $alpha = max($keyedAlpha, $sourceAlpha);

                if ($alpha >= $fullyTransparent) {
                    continue;
                }

                imagesetpixel(
                    $keyed,
                    $x,
                    $y,
                    imagecolorallocatealpha($keyed, $red, $green, $blue, $alpha)
                );
            }
        }

        return $keyed;
    }

    /**
     * Encode the recolored image back to PNG bytes.
     */
    protected function encodePng(GdImage $image): ?string
    {
        ob_start();
        $written = imagepng($image);
        $bytes = ob_get_clean();

        if (! $written || $bytes === false || $bytes === '') {
            Log::warning('Signature image could not be encoded; cover letter signature skipped.', [
                'path' => $this->sourcePath,
            ]);

            return null;
        }

        return $bytes;
    }
}
