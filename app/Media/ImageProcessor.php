<?php

namespace App\Media;

use GdImage;
use RuntimeException;

/**
 * Turns an uploaded photo into what we actually publish:
 *
 *   - RE-ENCODED, so nothing but pixels survives: no EXIF, no GPS location
 *     from the seller's phone, no camera serial, no hidden payload riding
 *     along inside the file.
 *   - Rotated upright first, using the EXIF Orientation tag (phones store
 *     "rotate me 90°" as metadata; once metadata is gone the photo would
 *     otherwise show sideways).
 *   - Two sizes, WebP: a main image and a light thumbnail for grids.
 */
class ImageProcessor
{
    public const MAIN_MAX = 1600;

    public const THUMB_MAX = 480;

    private const QUALITY = 82;

    /**
     * @return array{main: string, thumb: string} WebP bytes
     */
    public function process(string $path): array
    {
        $bytes = file_get_contents($path);
        $image = $bytes === false ? false : @imagecreatefromstring($bytes);

        if (! $image instanceof GdImage) {
            throw new RuntimeException('Not a readable image.');
        }

        $image = $this->upright($image, $path);

        return [
            'main' => $this->encode($this->fit($image, self::MAIN_MAX)),
            'thumb' => $this->encode($this->fit($image, self::THUMB_MAX)),
        ];
    }

    /**
     * EXIF Orientation: 3 = upside down, 6 = rotate 90° clockwise,
     * 8 = rotate 90° counter-clockwise (2/4/5/7 are mirrored versions,
     * rare from cameras; mirrored ones are flipped then rotated).
     */
    private function upright(GdImage $image, string $path): GdImage
    {
        $exif = @exif_read_data($path); // only JPEGs carry it; others return false
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }

        // imagerotate() turns counter-clockwise.
        $angle = match ($orientation) {
            3, 4 => 180,
            6, 5 => 270,
            8, 7 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);

        return $rotated instanceof GdImage ? $rotated : $image;
    }

    /** Shrink to fit inside $max × $max, keeping the aspect ratio. Never enlarge. */
    private function fit(GdImage $image, int $max): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, $max / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        // Keep transparency from PNGs.
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $canvas;
    }

    private function encode(GdImage $image): string
    {
        ob_start();
        imagewebp($image, null, self::QUALITY);

        return (string) ob_get_clean();
    }
}
