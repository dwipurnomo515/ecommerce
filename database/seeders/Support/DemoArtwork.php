<?php

namespace Database\Seeders\Support;

use GdImage;
use InvalidArgumentException;

/**
 * "Studio shot" illustrations for the demo products, drawn with GD.
 *
 * Why drawn and not downloaded: no network needed during seeding, no
 * third-party photo licences in the repository, and every demo product is
 * labelled "(Contoh)" anyway. The results go through the same pipeline as a
 * seller upload (ProductImage::storeProcessed), so WebP + thumbnails too.
 *
 * Everything is drawn at 2x and scaled down: GD's filled shapes have no
 * anti-aliasing, the downscale smooths the edges.
 *
 * Coordinates below are in a 1200 x 1200 space.
 */
final class DemoArtwork
{
    public const ITEMS = ['tee', 'hat', 'tote', 'mug', 'bottle'];

    private const SIZE = 1200;

    private const SCALE = 2;

    /** Main shot per item: [x, y, size], framing the item at roughly 70%. */
    private const FULL = [
        'tee' => [150, 150, 900],
        'hat' => [200, 110, 800],
        'tote' => [220, 205, 760],
        'mug' => [300, 290, 640],
        'bottle' => [190, 150, 820],
    ];

    /** Detail shot per item: [x, y, size] of the square to zoom into. */
    private const DETAIL = [
        'tee' => [330, 200, 540],
        'hat' => [380, 300, 440],
        'tote' => [420, 470, 360],
        'mug' => [560, 420, 360],
        'bottle' => [440, 190, 320],
    ];

    /**
     * Two temporary image files: the full shot and a close-up.
     * The caller deletes them after use.
     *
     * @return array{0: string, 1: string}
     */
    public static function shots(string $item): array
    {
        if (! in_array($item, self::ITEMS, true)) {
            throw new InvalidArgumentException("Unknown demo item [{$item}].");
        }

        $canvas = self::draw($item);

        return [
            self::save(self::crop($canvas, ...self::FULL[$item])),
            self::save(self::crop($canvas, ...self::DETAIL[$item])),
        ];
    }

    private static function draw(string $item): GdImage
    {
        $size = self::SIZE * self::SCALE;
        $im = imagecreatetruecolor($size, $size);
        imagealphablending($im, true);

        // Soft sage backdrop, a touch darker towards the bottom.
        for ($y = 0; $y < $size; $y++) {
            $t = $y / $size;
            imageline($im, 0, $y, $size, $y, self::color($im, 238 - 16 * $t, 242 - 14 * $t, 239 - 15 * $t));
        }

        match ($item) {
            'tee' => self::tee($im),
            'hat' => self::hat($im),
            'tote' => self::tote($im),
            'mug' => self::mug($im),
            'bottle' => self::bottle($im),
            default => throw new InvalidArgumentException("Unknown demo item [{$item}]."),
        };

        return $im;
    }

    private static function tee(GdImage $im): void
    {
        self::shadow($im, 600, 925, 540, 56);

        $fabric = self::color($im, 251, 251, 249);
        $fold = self::color($im, 0, 0, 0, 119);

        $outline = [
            520, 290, 400, 325, 250, 465, 318, 548, 420, 488, 425, 905,
            775, 905, 780, 488, 882, 548, 950, 465, 800, 325, 680, 290,
        ];

        // A faint edge first: white cotton on a light backdrop needs it.
        imagesetthickness($im, 3 * self::SCALE);
        imagepolygon($im, array_map(fn (int $v): int => $v * self::SCALE, $outline), self::color($im, 160, 170, 166));
        imagesetthickness($im, 1);
        self::polygon($im, $outline, $fabric);

        // Inside of the back, seen through the neckline, and the collar rib.
        self::arc($im, 600, 290, 164, 104, 0, 180, self::color($im, 228, 229, 225));
        self::ring($im, 600, 290, 164, 104, 12, 0, 180, self::color($im, 233, 233, 229));

        // Folds and seams: very light, like daylight on cotton.
        self::polygon($im, [425, 488, 462, 492, 458, 905, 425, 905], $fold);
        self::polygon($im, [740, 492, 780, 488, 775, 905, 744, 905], $fold);
        self::line($im, 432, 872, 768, 872, 3, self::color($im, 0, 0, 0, 112));
        self::line($im, 268, 482, 330, 535, 3, self::color($im, 0, 0, 0, 112));
        self::line($im, 932, 482, 870, 535, 3, self::color($im, 0, 0, 0, 112));
        self::line($im, 420, 488, 400, 330, 2, self::color($im, 0, 0, 0, 116));
        self::line($im, 780, 488, 800, 330, 2, self::color($im, 0, 0, 0, 116));
    }

    private static function hat(GdImage $im): void
    {
        self::shadow($im, 600, 690, 640, 80);

        $olive = self::color($im, 116, 121, 81);

        self::ellipse($im, 600, 600, 640, 190, self::color($im, 82, 86, 56));
        self::ellipse($im, 600, 588, 630, 176, self::color($im, 110, 115, 76));

        foreach ([560, 500] as $w) {
            self::ring($im, 600, 588, $w, (int) ($w * 0.28), 1, 0, 360, self::color($im, 0, 0, 0, 108));
        }

        self::ellipse($im, 600, 588, 304, 56, $olive);
        self::polygon($im, [448, 588, 470, 360, 730, 360, 752, 588], $olive);
        self::ellipse($im, 600, 360, 260, 70, self::color($im, 130, 135, 93));
        self::polygon($im, [460, 528, 465, 476, 735, 476, 740, 528], self::color($im, 66, 70, 45));
        self::polygon($im, [474, 380, 520, 372, 508, 580, 456, 584], self::color($im, 255, 255, 255, 116));
    }

    private static function tote(GdImage $im): void
    {
        self::shadow($im, 600, 880, 470, 52);

        $strap = self::color($im, 190, 171, 138);
        self::ring($im, 520, 440, 110, 320, 16, 180, 360, $strap);
        self::ring($im, 680, 440, 110, 320, 16, 180, 360, $strap);

        self::polygon($im, [400, 430, 800, 430, 822, 870, 378, 870], self::color($im, 232, 221, 199));
        self::polygon($im, [400, 430, 800, 430, 802, 472, 398, 472], self::color($im, 220, 207, 181));

        for ($x = 410; $x < 790; $x += 22) {
            self::line($im, $x, 482, $x + 11, 482, 2, self::color($im, 150, 132, 100));
        }

        // A small pine-green mark printed on the canvas.
        self::ellipse($im, 600, 670, 150, 150, self::color($im, 13, 92, 85));
        self::polygon($im, [
            566, 632, 634, 632, 634, 646, 588, 694, 634, 694, 634, 708,
            566, 708, 566, 694, 612, 646, 566, 646,
        ], self::color($im, 246, 243, 235));

        self::polygon($im, [760, 472, 802, 472, 822, 870, 772, 870], self::color($im, 0, 0, 0, 118));
    }

    private static function mug(GdImage $im): void
    {
        self::shadow($im, 590, 812, 400, 52);

        $glaze = self::color($im, 13, 92, 85);

        self::ring($im, 728, 610, 150, 200, 28, 270, 90, $glaze);
        self::rectangle($im, 450, 420, 720, 800, $glaze);
        self::ellipse($im, 585, 800, 270, 60, $glaze);
        self::ellipse($im, 585, 420, 270, 72, self::color($im, 34, 118, 109));
        self::ellipse($im, 585, 425, 238, 54, self::color($im, 70, 44, 30));
        self::polygon($im, [478, 448, 506, 450, 506, 790, 478, 786], self::color($im, 255, 255, 255, 108));
        self::polygon($im, [680, 448, 720, 440, 720, 800, 684, 806], self::color($im, 0, 0, 0, 112));
    }

    private static function bottle(GdImage $im): void
    {
        self::shadow($im, 600, 892, 280, 40);

        $steel = self::color($im, 152, 170, 178);

        self::rectangle($im, 552, 228, 648, 305, self::color($im, 38, 42, 46));
        self::ellipse($im, 600, 228, 96, 22, self::color($im, 62, 66, 70));
        self::rectangle($im, 566, 300, 634, 400, $steel);
        self::ellipse($im, 600, 428, 172, 120, $steel);
        self::rectangle($im, 514, 428, 686, 880, $steel);
        self::ellipse($im, 600, 880, 172, 40, $steel);
        self::rectangle($im, 514, 590, 686, 720, self::color($im, 13, 92, 85));
        self::polygon($im, [534, 430, 556, 420, 556, 872, 534, 868], self::color($im, 255, 255, 255, 100));
        self::polygon($im, [654, 420, 686, 430, 686, 880, 656, 886], self::color($im, 0, 0, 0, 112));
    }

    // ------------------------------------------------------------------ helpers

    private static function shadow(GdImage $im, int $cx, int $cy, int $w, int $h): void
    {
        // Many faint ellipses stacked: dark in the middle, soft at the edge.
        for ($i = 0; $i < 36; $i++) {
            $f = 1 - $i / 40;
            self::ellipse($im, $cx, $cy, (int) ($w * $f), (int) ($h * $f), self::color($im, 30, 50, 45, 123));
        }
    }

    /** @param  list<int>  $points */
    private static function polygon(GdImage $im, array $points, int $color): void
    {
        imagefilledpolygon($im, array_map(fn (int $v): int => $v * self::SCALE, $points), $color);
    }

    private static function ellipse(GdImage $im, int $cx, int $cy, int $w, int $h, int $color): void
    {
        imagefilledellipse($im, $cx * self::SCALE, $cy * self::SCALE, $w * self::SCALE, $h * self::SCALE, $color);
    }

    private static function arc(GdImage $im, int $cx, int $cy, int $w, int $h, int $start, int $end, int $color): void
    {
        imagefilledarc($im, $cx * self::SCALE, $cy * self::SCALE, $w * self::SCALE, $h * self::SCALE, $start, $end, $color, IMG_ARC_PIE);
    }

    /**
     * A thick outline arc. GD ignores the line thickness for imagearc (and
     * stacking 1px arcs leaves speckled gaps), so the arc is walked as short
     * thick line segments with round joints.
     */
    private static function ring(GdImage $im, int $cx, int $cy, int $w, int $h, int $thickness, int $start, int $end, int $color): void
    {
        if ($thickness <= 1) {
            // Hairlines: overlapping joints would darken translucent colours.
            imagearc($im, $cx * self::SCALE, $cy * self::SCALE, $w * self::SCALE, $h * self::SCALE, $start, $end, $color);

            return;
        }

        $end = $end <= $start ? $end + 360 : $end;
        $radius = intdiv($thickness * self::SCALE, 2);
        $previous = null;

        imagesetthickness($im, $thickness * self::SCALE);

        for ($angle = $start; $angle <= $end; $angle += 2) {
            $point = [
                (int) round(($cx + $w / 2 * cos(deg2rad($angle))) * self::SCALE),
                (int) round(($cy + $h / 2 * sin(deg2rad($angle))) * self::SCALE),
            ];

            if ($previous !== null) {
                imageline($im, $previous[0], $previous[1], $point[0], $point[1], $color);
            }

            imagefilledellipse($im, $point[0], $point[1], $radius * 2, $radius * 2, $color);
            $previous = $point;
        }

        imagesetthickness($im, 1);
    }

    private static function rectangle(GdImage $im, int $x1, int $y1, int $x2, int $y2, int $color): void
    {
        imagefilledrectangle($im, $x1 * self::SCALE, $y1 * self::SCALE, $x2 * self::SCALE, $y2 * self::SCALE, $color);
    }

    private static function line(GdImage $im, int $x1, int $y1, int $x2, int $y2, int $thickness, int $color): void
    {
        imagesetthickness($im, $thickness * self::SCALE);
        imageline($im, $x1 * self::SCALE, $y1 * self::SCALE, $x2 * self::SCALE, $y2 * self::SCALE, $color);
        imagesetthickness($im, 1);
    }

    /** @param  int<0, 127>  $alpha  0 = opaque, 127 = invisible */
    private static function color(GdImage $im, float $r, float $g, float $b, int $alpha = 0): int
    {
        return (int) imagecolorallocatealpha($im, self::channel($r), self::channel($g), self::channel($b), $alpha);
    }

    /** @return int<0, 255> */
    private static function channel(float $value): int
    {
        return max(0, min(255, (int) round($value)));
    }

    private static function crop(GdImage $canvas, int $x, int $y, int $size): GdImage
    {
        $out = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagecopyresampled($out, $canvas, 0, 0, $x * self::SCALE, $y * self::SCALE, self::SIZE, self::SIZE, $size * self::SCALE, $size * self::SCALE);

        return $out;
    }

    private static function save(GdImage $image): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'demo-art');
        imagepng($image, $path);

        return $path;
    }
}
