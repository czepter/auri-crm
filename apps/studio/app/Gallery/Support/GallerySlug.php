<?php

namespace App\Gallery\Support;

use Illuminate\Support\Str;

/**
 * The readable half of a gallery's address (`galleries.slug`), unique within
 * its `prefix`. The prefix is formatted the same way and shares the reserved
 * words, since it is the first segment on apps/gallery's host.
 *
 * Bounds: MIN 3 keeps a shared, un-tenanted namespace from filling with
 * meaningless two-letter handles; MAX 60 keeps a link glanceable in an email
 * or a share sheet while comfortably outliving any real gallery title
 * (`galleries.title` itself caps at 255, Laravel's default string length —
 * a slug is a compressed address, not a mirror of it).
 *
 * RESERVED is the static segments `/galerien/` registers above `{gallery}`
 * in routes/admin.php, plus the first segments apps/gallery serves itself
 * (`api`, `health`, `build`, its asset paths, the legacy `g`) and the
 * `download` page under an address. `extern` is in ahead of
 * `/galerien/extern/neu`, which is not a route yet.
 */
class GallerySlug
{
    public const MIN = 3;

    public const MAX = 60;

    public const RESERVED = ['neu', 'extern', 'api', 'health', 'build', 'assets', '_astro', 'g', 'download'];

    public const PATTERN = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    public static function format(string $value): string
    {
        $slug = Str::of($value)->slug('-', 'de')->substr(0, self::MAX)->rtrim('-')->value();

        return $slug === '' ? 'galerie' : $slug;
    }

    /**
     * The formatted slug, or the same with a growing numeric suffix until
     * $isTaken says no — reserved words and the MIN-length floor count as
     * taken too, so a short or reserved title never comes back unmodified.
     */
    public static function unique(string $seed, callable $isTaken): string
    {
        $base = self::format($seed);
        $candidate = $base;
        $suffix = 2;

        while (self::invalid($candidate) || $isTaken($candidate)) {
            $suffixed = '-'.$suffix;
            $candidate = Str::substr($base, 0, self::MAX - strlen($suffixed)).$suffixed;
            $suffix++;
        }

        return $candidate;
    }

    private static function invalid(string $candidate): bool
    {
        return strlen($candidate) < self::MIN || in_array($candidate, self::RESERVED, true);
    }
}
