<?php

namespace App\Media\Support;

/**
 * Parses a single-range `Range: bytes=start-end` header against a known
 * total size (G-11, #60) — pure logic, unit-tested in isolation the same
 * way ZipVolumePlanner's own grouping logic is. This is the piece
 * `Storage::download()` has no equivalent of at all: it never looks at the
 * request's Range header, so every resume it served would restart from
 * byte zero. DownloadZipPartController is what makes that not true.
 *
 * Multipart ranges (`bytes=0-99,200-299`) are not supported — no client
 * this app serves sends one, and RFC 9110 permits refusing what a single
 * `Content-Range` response cannot express.
 */
final class ByteRange
{
    private function __construct(
        public readonly int $start,
        public readonly int $end,
    ) {}

    /** Null means "no Range header" — the caller serves the whole object as a plain 200. */
    public static function parse(?string $header, int $totalSize): ?self
    {
        if ($header === null || trim($header) === '') {
            return null;
        }

        if (! preg_match('/^bytes=(\d*)-(\d*)$/', trim($header), $matches) || $totalSize <= 0) {
            throw new RangeNotSatisfiableException($totalSize);
        }

        [, $rawStart, $rawEnd] = $matches;

        if ($rawStart === '' && $rawEnd === '') {
            throw new RangeNotSatisfiableException($totalSize);
        }

        if ($rawStart === '') {
            // A suffix range: the last N bytes.
            $start = max(0, $totalSize - (int) $rawEnd);
            $end = $totalSize - 1;
        } else {
            $start = (int) $rawStart;
            $end = $rawEnd === '' ? $totalSize - 1 : min((int) $rawEnd, $totalSize - 1);
        }

        if ($start > $end || $start >= $totalSize) {
            throw new RangeNotSatisfiableException($totalSize);
        }

        return new self($start, $end);
    }

    public function length(): int
    {
        return $this->end - $this->start + 1;
    }

    /** The `Range` value S3's own GetObject expects. */
    public function forS3(): string
    {
        return "bytes={$this->start}-{$this->end}";
    }

    /** The `Content-Range` header value this range's response carries. */
    public function contentRange(int $totalSize): string
    {
        return "bytes {$this->start}-{$this->end}/{$totalSize}";
    }
}
