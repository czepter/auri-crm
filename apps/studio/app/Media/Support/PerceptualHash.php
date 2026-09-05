<?php

namespace App\Media\Support;

use RuntimeException;

/**
 * A hand-rolled DCT-based pHash (per #52: "a well-known small library... or
 * a hand-rolled DCT-based pHash is acceptable, keep it minimal") — no
 * composer package exists in this repo for it, and the algorithm itself is
 * a couple dozen lines given a 32x32 grayscale source already decoded by
 * ext-gd, which composer.json already requires.
 *
 * The standard phash.org recipe: a 32x32 greyscale DCT, keep the 8x8
 * lowest-frequency block, threshold every coefficient against the block's
 * own mean (excluding the DC term, which is dominated by overall
 * brightness rather than structure) — 64 bits, rendered as 16 hex chars.
 * Two visually similar images land close in Hamming distance; this story
 * only computes and stores the hash; comparing hashes for near-duplicates
 * is a future consumer's job, not this one's.
 */
class PerceptualHash
{
    private const SIZE = 32;

    private const LOW_FREQUENCY = 8;

    public function hash(string $squarePngPath): string
    {
        $image = @imagecreatefrompng($squarePngPath);
        if ($image === false) {
            throw new RuntimeException("Unable to decode {$squarePngPath} for perceptual hashing.");
        }

        try {
            $matrix = $this->grayscaleMatrix($image);
            $dct = $this->dct2d($matrix);

            $values = [];
            for ($y = 0; $y < self::LOW_FREQUENCY; $y++) {
                for ($x = 0; $x < self::LOW_FREQUENCY; $x++) {
                    $values[] = $dct[$y][$x];
                }
            }

            // Exclude the DC term (index 0: the block's overall brightness,
            // not its structure) from the average the bits are thresholded
            // against — the same exclusion phash.org's own recipe applies.
            $mean = (array_sum($values) - $values[0]) / (count($values) - 1);

            $bits = '';
            foreach ($values as $value) {
                $bits .= $value > $mean ? '1' : '0';
            }

            // 64 bits, converted 4 at a time rather than through a single
            // bindec() on the whole string — bindec() falls back to float
            // arithmetic past ~31 bits and silently loses precision on a
            // value this wide.
            $hex = '';
            foreach (str_split($bits, 4) as $nibble) {
                $hex .= dechex((int) bindec($nibble));
            }

            return strtoupper($hex);
        } finally {
            imagedestroy($image);
        }
    }

    /** @return list<list<float>> */
    private function grayscaleMatrix(\GdImage $image): array
    {
        $matrix = [];
        for ($y = 0; $y < self::SIZE; $y++) {
            $row = [];
            for ($x = 0; $x < self::SIZE; $x++) {
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $row[] = 0.299 * $r + 0.587 * $g + 0.114 * $b;
            }
            $matrix[] = $row;
        }

        return $matrix;
    }

    /**
     * A separable 2D DCT-II: a 1D DCT over every row, then over every
     * column of the result. O(n^3) for n=32 is ~33k multiply-adds — trivial
     * at this size, so no FFT-based shortcut is worth the complexity.
     *
     * @param  list<list<float>>  $matrix
     * @return list<list<float>>
     */
    private function dct2d(array $matrix): array
    {
        $rows = array_map($this->dct1d(...), $matrix);

        $columns = [];
        for ($x = 0; $x < self::SIZE; $x++) {
            $columns[] = $this->dct1d(array_column($rows, $x));
        }

        $result = [];
        for ($y = 0; $y < self::SIZE; $y++) {
            $row = [];
            for ($x = 0; $x < self::SIZE; $x++) {
                $row[] = $columns[$x][$y];
            }
            $result[] = $row;
        }

        return $result;
    }

    /**
     * @param  list<float>  $signal
     * @return list<float>
     */
    private function dct1d(array $signal): array
    {
        $n = count($signal);
        $output = [];

        for ($k = 0; $k < $n; $k++) {
            $sum = 0.0;
            for ($i = 0; $i < $n; $i++) {
                $sum += $signal[$i] * cos(M_PI / $n * ($i + 0.5) * $k);
            }
            $output[] = $sum;
        }

        return $output;
    }
}
