<?php

namespace App\Settings\Support;

use App\Models\User;
use App\Settings\Models\StudioSettings;
use App\Support\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

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

    /**
     * The stored path of the logo — the ONE file both RenderContractJob's
     * `studio.logoPath` merge field and a future portal-branding read are
     * meant to resolve against (T-02's acceptance criterion). Never a second
     * upload for a second context: whoever needs the logo reads it through
     * here.
     */
    public function logoPath(): ?string
    {
        return $this->row()->logo_path;
    }

    /**
     * T-02's legal-entity, brand and bank columns, keyed by column name —
     * the one place outside this class permitted to see them, and only as
     * plain values. The Studio-Profil edit screen and RenderContractJob's
     * `studio.*` merge fields (docs/contracts.md) both read through this
     * rather than through row() itself, which stays private.
     *
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return Arr::only($this->row()->getAttributes(), [
            'display_name',
            'legal_name', 'legal_form', 'street', 'postcode', 'city', 'country',
            'tax_number', 'vat_id', 'register_number', 'register_court', 'representative',
            'website', 'accent_color', 'logo_path',
            'bank_account_holder', 'bank_iban', 'bank_bic',
        ]);
    }

    /**
     * The logo is stored once (T-02's acceptance criterion) — a re-upload
     * replaces the same tenant's file and removes whatever it replaces,
     * rather than accumulating a second one under a second name. Uses the
     * `local` disk the exact way RenderContractJob stores a rendered
     * contract (Storage::disk('local'), see docs/contracts.md), not a
     * second filesystem convention invented for branding alone.
     */
    public function setLogo(UploadedFile $file, ?User $actor = null): void
    {
        $row = StudioSettings::query()->firstOrFail();
        $old = $row->logo_path;

        $path = $file->storeAs("branding/{$row->tenant_id}", 'logo.'.$file->extension(), 'local');

        if ($old !== null && $old !== $path) {
            Storage::disk('local')->delete($old);
        }

        $row->update(['logo_path' => $path]);

        Cache::store('redis')->forget($this->cacheKey());

        AuditLogger::record(
            event: 'studio_settings.updated',
            actor: $actor,
            context: ['key' => 'logo_path', 'old' => $old, 'new' => $path],
            subject: $row->public_id,
        );
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
