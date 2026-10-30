<?php

namespace App\Media\Support;

use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * The one seam past raw `vipsthumbnail`/`vipsheader` shell-outs (per #52:
 * "check if... available/installable in this repo's Docker image" —
 * docker/php/Dockerfile installs `libvips-tools`). No PHP binding (ext-vips,
 * jcupitt/vips) is installed or needed: every call here is a single CLI
 * invocation, and Illuminate\Support\Facades\Process runs it without a shell
 * in between, so vips's own bracket option syntax
 * (`out.avif[Q=60,effort=2,strip]`) never needs escaping.
 *
 * Every vipsthumbnail call pins --vips-concurrency=1 — the P3 gallery
 * spike's own measured decision: many single-threaded workers beat few
 * multi-threaded ones on this workload, so concurrency comes from Horizon's
 * supervisor-media-ingest worker count (config/horizon.php), never from
 * libvips threading inside one job.
 *
 * A source this cannot decode at all (a corrupt file, a format this
 * libvips build has no loader for) throws from dimensions() before any
 * derivative work starts — App\Gallery\Actions\IngestCompletedUpload turns
 * that into a Failed asset rather than an unhandled job failure.
 */
class LibvipsDerivativePipeline
{
    /**
     * The ladder per the P3 gallery spike: an 800 px rung added for 2x DPR
     * beyond the vision's original 400/1600/2560, clamped downward for a
     * source narrower than a given rung — never upscaled.
     */
    private const WIDTHS = [2560, 1600, 800, 400];

    /**
     * effort=2 over the libvips default of 4 — 2.8x cheaper at 2560px for a
     * low-single-digit-percent size cost (spike measurement, §2).
     */
    private const AVIF_OPTIONS = 'Q=60,effort=2,strip';

    private const WEBP_OPTIONS = 'Q=80,strip';

    /** @return array{width: int, height: int} */
    public function dimensions(string $path): array
    {
        return [
            'width' => $this->headerInt($path, 'width'),
            'height' => $this->headerInt($path, 'height'),
        ];
    }

    /**
     * The full AVIF+WebP ladder, chained from a single decode of $path
     * rather than re-decoding it once per width (1.8x cheaper, spike
     * measurement, §2a): each rung resizes down from the previous rung's
     * own (already small) output, never from $path again, so only the very
     * first — largest — rung ever decodes the full-size original.
     *
     * @return list<array{width: int, format: string, path: string, bytes: int}>
     */
    public function ladder(string $path, int $originalWidth, string $workDir): array
    {
        $widths = array_values(array_filter(self::WIDTHS, fn (int $w) => $w <= $originalWidth));
        if ($widths === []) {
            // Never upscale — a source narrower than the smallest rung gets
            // exactly one derivative set, at its own native width.
            $widths = [$originalWidth];
        }
        rsort($widths);

        $source = $path;
        $outputs = [];

        foreach ($widths as $width) {
            $intermediate = "{$workDir}/step-{$width}.v";
            $this->vipsthumbnail($source, $width, $intermediate);

            foreach (['avif' => self::AVIF_OPTIONS, 'webp' => self::WEBP_OPTIONS] as $format => $options) {
                $out = "{$workDir}/out-{$width}.{$format}";
                $this->vipsthumbnail($intermediate, $width, "{$out}[{$options}]");
                $outputs[] = ['width' => $width, 'format' => $format, 'path' => $out, 'bytes' => filesize($out)];
            }

            // The chain: the next (smaller) rung decodes this rung's own
            // output, never the original again.
            $source = $intermediate;
        }

        return $outputs;
    }

    /** A fixed-size, aspect-ignoring square thumbnail — the source PerceptualHash::hash() decodes. */
    public function squareThumbnail(string $path, string $outPath, int $size): void
    {
        $this->run(['vipsthumbnail', $path, '--size', "{$size}x{$size}!", '--vips-concurrency=1', '-o', "{$outPath}[strip]"]);
    }

    /** An aspect-preserving thumbnail — the source a Blurhash placeholder encodes. */
    public function aspectThumbnail(string $path, string $outPath, int $size): void
    {
        $this->run(['vipsthumbnail', $path, '--size', (string) $size, '--vips-concurrency=1', '-o', "{$outPath}[strip]"]);
    }

    private function vipsthumbnail(string $source, int $width, string $output): void
    {
        $this->run(['vipsthumbnail', $source, '--size', (string) $width, '--vips-concurrency=1', '-o', $output]);
    }

    private function headerInt(string $path, string $field): int
    {
        return (int) trim($this->run(['vipsheader', '-f', $field, $path]));
    }

    private function run(array $command): string
    {
        $result = Process::run($command);

        if ($result->failed()) {
            throw new RuntimeException("`{$command[0]}` failed: ".trim($result->errorOutput() ?: $result->output()));
        }

        return $result->output();
    }
}
