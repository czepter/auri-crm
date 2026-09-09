<?php

namespace App\Releases\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * What the studio knows about its own versions. The data lives in release-log,
 * a separate service that reads the portal repo's tags; this class is the only
 * thing here that talks to it.
 *
 * Nothing in a request reaches that service: FetchReleasesCommand writes these
 * keys on a schedule and the screens read them. A service that is down or slow
 * is then a screen showing yesterday's list, not a spinner in the sidebar.
 *
 * Cache::forever rather than a day's TTL, which is the same decision from the
 * other side: the refresh runs daily, and a TTL matched to it would empty the
 * screen the first time a run failed. Staleness is the acceptable failure
 * here; emptiness is not.
 */
class ReleaseLog
{
    private const FEED = 'release-log.feed';

    private const LATEST = 'release-log.latest';

    private const RELEASE = 'release-log.release.';

    /** Every release in one page; the service caps per_page at 100. */
    private const PER_PAGE = 100;

    public function base(): ?string
    {
        $url = config('services.release_log.url');

        return $url === null ? null : rtrim((string) $url, '/');
    }

    /**
     * Pulls the feed and every release behind it. Returns how many releases
     * were stored, or -1 when no service is configured — the deployment
     * without a release-log is a working deployment, just one without this
     * screen.
     */
    public function refresh(): int
    {
        $base = $this->base();

        if ($base === null) {
            return -1;
        }

        $versions = Http::get("{$base}/versions")->throw()->json();
        $feed = Http::get("{$base}/releases", ['per_page' => self::PER_PAGE])->throw()->json();

        foreach ($feed['releases'] ?? [] as $release) {
            $version = $release['version'] ?? null;

            if ($version === null) {
                continue;
            }

            Cache::forever(
                self::RELEASE.$version,
                Http::get("{$base}/releases/{$version}")->throw()->json(),
            );
        }

        Cache::forever(self::FEED, $feed['releases'] ?? []);
        Cache::forever(self::LATEST, $versions['latest'] ?? null);

        return count($feed['releases'] ?? []);
    }

    /** @return array<int, array<string, mixed>> */
    public function feed(): array
    {
        return Cache::get(self::FEED, []);
    }

    /** @return array<string, mixed>|null */
    public function release(string $version): ?array
    {
        return Cache::get(self::RELEASE.$version);
    }

    public function latest(): ?string
    {
        return Cache::get(self::LATEST);
    }

    /** Baked into the image by docker/app/Dockerfile; `dev` off a branch. */
    public function installed(): string
    {
        return (string) config('app.version');
    }

    /**
     * Whether release-log knows a version this deployment does not run.
     *
     * The guard on the leading digit is not defensive: a branch build calls
     * itself `dev`, and version_compare sorts that below every number, so
     * without it every dev build reports itself out of date.
     */
    public function updateAvailable(): bool
    {
        $latest = $this->latest();
        $installed = $this->installed();

        if ($latest === null || preg_match('/^\d/', $installed) !== 1) {
            return false;
        }

        return version_compare($installed, $latest, '<');
    }
}
