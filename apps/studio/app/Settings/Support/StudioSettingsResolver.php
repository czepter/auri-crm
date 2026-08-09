<?php

namespace App\Settings\Support;

use App\Models\User;
use App\Settings\Models\StudioSettings;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\Cache;

/**
 * The tenant-scoped config service Vision §12 asked for and nothing built
 * until T-01. Injected into whatever needs a setting — never a
 * `Settings::get('key')` facade reaching into a global — so a caller's
 * dependency on the settings store shows up in its own constructor.
 *
 * Always reads and invalidates through the `redis` store by name, never
 * config('cache.default') (`array` in tests, `database` otherwise — see
 * config/cache.php and phpunit.xml). M-00 put a Horizon worker beside the
 * web container specifically so a value the web request just changed is
 * never rendered stale into a document the worker is building. An array or
 * file cache only ever invalidates inside the one process that wrote it;
 * Redis is the one backend both processes actually share, with no deploy
 * and no cache-clear command in between.
 */
class StudioSettingsResolver
{
    private const CACHE_KEY_PREFIX = 'studio_settings:';

    private const TTL = 3600;

    public function displayName(): ?string
    {
        return $this->row()->display_name;
    }

    public function publicId(): string
    {
        return $this->row()->public_id;
    }

    /** 'standard' or 'small_business' — see the studio_settings table's check constraint for the domain. */
    public function vatMode(): string
    {
        return $this->row()->vat_mode;
    }

    /**
     * The standard-mode rate, in basis points (1900 = 19.00%) — never zero
     * out this value on a small_business switch, or switching back to
     * standard has nothing to restore. Q-04 wants effectiveVatRate()
     * instead of this for anything it actually prints.
     */
    public function vatRate(): int
    {
        return $this->row()->vat_rate;
    }

    public function vatNote(): ?string
    {
        return $this->row()->vat_note;
    }

    /**
     * What a document should actually use — zero under small_business
     * regardless of what vat_rate holds, so a mode switch never leaves a
     * stale 19% behind. This is T-03's whole point: Q-04 calls this, not
     * vatRate(), the moment it prints a total.
     */
    public function effectiveVatRate(): int
    {
        return $this->vatMode() === 'small_business' ? 0 : $this->vatRate();
    }

    /**
     * Writes one column, busts the cache both processes read through, and
     * leaves an audit row naming the actor and both values — T-01's issue,
     * "every write goes through the existing AuditLog". Reads the row fresh
     * off the database rather than through row()'s cache, so a stale cached
     * copy can never write a wrong "old" value into the audit trail.
     *
     * A no-op write (old === new) leaves neither an audit row nor a cache
     * bust: "every change" in the acceptance criteria, not every request.
     */
    public function set(string $key, mixed $value, ?User $actor = null): void
    {
        $row = StudioSettings::query()->firstOrFail();
        $old = $row->{$key};

        if ($old === $value) {
            return;
        }

        $row->update([$key => $value]);

        Cache::store('redis')->forget($this->cacheKey());

        AuditLogger::record(
            event: 'studio_settings.updated',
            actor: $actor,
            context: ['key' => $key, 'old' => $old, 'new' => $value],
            subject: $row->public_id,
        );
    }

    private function row(): StudioSettings
    {
        return Cache::store('redis')->remember(
            $this->cacheKey(),
            self::TTL,
            fn () => StudioSettings::query()->firstOrFail(),
        );
    }

    private function cacheKey(): string
    {
        return self::CACHE_KEY_PREFIX.config('tenancy.tenant_id');
    }
}
