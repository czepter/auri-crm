<?php

namespace App\Media\Support;

use GdImage;

/**
 * Diagonal, halbtransparent über das ganze Bild gekachelt: ein Rand-Stempel
 * ließe sich abschneiden. Ohne Logo steht der Text; mit Logo das Logo, der
 * Text bleibt für Fälle, in denen das Studio keines hochgeladen hat.
 */
final class Watermark
{
    private const FONT = __DIR__.'/../../../resources/fonts/LiberationSans-Bold.ttf';

    private const ANGLE = 30;

    public function __construct(
        public readonly string $text,
        public readonly ?string $logoBytes = null,
    ) {}

    /** Trennt die zwischengespeicherten Bilder je Text und Logo-Stand. */
    public function key(): string
    {
        return substr(sha1($this->text.'|'.($this->logoBytes === null ? '' : sha1($this->logoBytes))), 0, 16);
    }

    public function paint(GdImage $image): void
    {
        imagealphablending($image, true);

        $mark = $this->logoBytes !== null ? $this->logoTile($image) : null;
        $mark !== null ? $this->tile($image, $mark) : $this->tileText($image);
    }

    private function tileText(GdImage $image): void
    {
        $width = imagesx($image);
        $size = max(14, (int) round($width / 22));
        $box = imagettfbbox($size, 0, self::FONT, $this->text);
        $stepX = (int) ($box[2] - $box[0]) + $size * 3;
        $stepY = $size * 7;

        $light = imagecolorallocatealpha($image, 255, 255, 255, 80);
        $dark = imagecolorallocatealpha($image, 0, 0, 0, 100);

        foreach ($this->grid($image, $stepX, $stepY) as [$x, $y]) {
            imagettftext($image, $size, self::ANGLE, $x + 1, $y + 1, $dark, self::FONT, $this->text);
            imagettftext($image, $size, self::ANGLE, $x, $y, $light, self::FONT, $this->text);
        }
    }

    private function tile(GdImage $image, GdImage $mark): void
    {
        foreach ($this->grid($image, imagesx($mark) * 2, imagesy($mark) * 2) as [$x, $y]) {
            imagecopy($image, $mark, $x, $y - imagesy($mark), 0, 0, imagesx($mark), imagesy($mark));
        }
    }

    /** @return iterable<array{int, int}> */
    private function grid(GdImage $image, int $stepX, int $stepY): iterable
    {
        $row = 0;

        for ($y = $stepY; $y < imagesy($image) + $stepY; $y += $stepY, $row++) {
            for ($x = ($row % 2) * intdiv($stepX, 2) - $stepX; $x < imagesx($image); $x += $stepX) {
                yield [$x, $y];
            }
        }
    }

    private function logoTile(GdImage $image): ?GdImage
    {
        $logo = @imagecreatefromstring((string) $this->logoBytes);
        if ($logo === false) {
            return null;
        }

        $width = max(60, (int) round(imagesx($image) / 6));
        $scaled = imagescale($logo, $width);
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);

        for ($y = 0; $y < imagesy($scaled); $y++) {
            for ($x = 0; $x < imagesx($scaled); $x++) {
                $alpha = (imagecolorat($scaled, $x, $y) >> 24) & 0x7F;
                imagesetpixel($scaled, $x, $y, (imagecolorat($scaled, $x, $y) & 0xFFFFFF) | ((127 - (int) ((127 - $alpha) * 0.45)) << 24));
            }
        }

        $rotated = imagerotate($scaled, self::ANGLE, imagecolorallocatealpha($scaled, 0, 0, 0, 127));
        imagealphablending($rotated, false);
        imagesavealpha($rotated, true);

        return $rotated;
    }
}
