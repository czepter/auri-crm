<?php

namespace App\Gallery\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Gallery\Support\GallerySlug;
use App\Media\Models\Asset;
use App\Media\Models\MultipartUpload;
use App\Settings\Support\StudioSettingsResolver;
use Carbon\CarbonImmutable;
use Database\Factories\Gallery\GalleryFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * A delivery gallery's draft state, plus the family every GalleryVersion
 * belongs to. shoot_id is nullable because a gallery frequently spans the
 * whole project rather than one shoot (G-05).
 *
 * title here is the working copy — freely editable, never what a guest is
 * served. draftAssets() is its counterpart for pictures, read live off the
 * assets table rather than stored on this row. publish() is the only way
 * their contents reach a GalleryVersion, and once written that row never
 * changes again; see publish() for why, and G-08 (version-scoped caching)
 * for the story that relies on it.
 *
 * G-06's three gates (view password, download PIN, verified email) live here
 * too, deliberately outside $fillable — the only way to set or clear one is
 * through its own rotate*() method below, so a version bump can never be
 * forgotten the way it could be from a plain attribute write.
 */
class Gallery extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = [
        'project_id', 'shoot_id', 'title', 'slug', 'design',
        'requires_settled_balance', 'balance_settled',
        'is_external', 'external_url', 'expires_at',
        'downloads_enabled', 'lists_enabled', 'comments_enabled',
        'event_date', 'cover_asset_id', 'cover_focal_x', 'cover_focal_y',
    ];

    protected $attributes = [
        'requires_settled_balance' => false,
        'balance_settled' => false,
        'is_external' => false,
        'downloads_enabled' => true,
        'lists_enabled' => true,
        'comments_enabled' => true,
        'requires_verified_email' => false,
        'view_password_version' => 1,
        'download_pin_version' => 1,
        'email_verification_version' => 1,
        'preview_pass_version' => 1,
    ];

    protected function casts(): array
    {
        return [
            // C-19 (#255): cover variant, typeface, colour and grid. Null
            // means "as the studio has it", so a studio changing its own
            // default still reaches every gallery that never overrode one.
            'design' => 'array',
            'requires_settled_balance' => 'boolean',
            'downloads_enabled' => 'boolean',
            'lists_enabled' => 'boolean',
            'comments_enabled' => 'boolean',
            'balance_settled' => 'boolean',
            'is_external' => 'boolean',
            // C-17 (#253): recoverable, because both values are handed to
            // guests anyway and a studio that cannot look one up locks out
            // everybody holding the old one.
            'view_password' => 'encrypted',
            'download_pin' => 'encrypted',
            'requires_verified_email' => 'boolean',
            'expires_at' => 'immutable_datetime',
            'event_date' => 'immutable_date',
            'unpublished_at' => 'immutable_datetime',
            'prerelease_at' => 'immutable_datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function shoot(): BelongsTo
    {
        return $this->belongsTo(Shoot::class);
    }

    /**
     * The seam that lets a slug change without ever breaking a link already
     * sitting in a guest's inbox: a `{gallery}` segment resolves by slug OR
     * public_id, here and only here, so admin, portal and api routes all
     * agree without each one carrying its own lookup.
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return static::findByPublicIdOrSlug($value);
    }

    public static function findByPublicIdOrSlug(string $value): ?self
    {
        return static::query()->where('slug', $value)->orWhere('public_id', $value)->first();
    }

    /**
     * apps/gallery's own address for this gallery — a separate host from
     * the studio this model lives on (config('services.gallery.url')). The
     * slug wins once set; public_id keeps resolving for ever, so an
     * already-sent link never breaks.
     */
    public function publicUrl(): string
    {
        return rtrim((string) config('services.gallery.url'), '/').'/g/'.($this->slug ?? $this->public_id);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(GalleryVersion::class);
    }

    public function sets(): HasMany
    {
        return $this->hasMany(GallerySet::class)->orderBy('position');
    }

    public function assetRows(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function coverAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'cover_asset_id');
    }

    /**
     * Die Entwurfsbilder in der Reihenfolge, in der sie ausgeliefert würden:
     * Sets in ihrer Position, innerhalb eines Sets nach dessen eigener
     * Regel. Fehlt taken_at — gescannt, exportiert, ohne EXIF —, traegt der
     * Dateiname die Reihenfolge; created_at waere die Reihenfolge, in der
     * die Queue fertig wurde, und die ist bei parallelen Workern Zufall.
     */
    public function draftAssets(): Collection
    {
        return $this->sets()->with(['assets' => fn ($query) => $query->inDraft()])->get()
            ->flatMap(fn (GallerySet $set) => $set->assets
                ->sortBy([
                    // Null sorts first by <=>, but a missing taken_at (scanned,
                    // exported, no EXIF) is a fallback case, not a "comes
                    // first" case — it must lose to every dated asset.
                    [fn (Asset $a, Asset $b) => ($a->{$set->sort_key} === null) <=> ($b->{$set->sort_key} === null), 'asc'],
                    [$set->sort_key, $set->sort_dir],
                    ['filename', 'asc'],
                ])
                ->map(fn (Asset $asset) => [
                    'key' => $asset->key,
                    'set' => $set->name,
                    'width' => $asset->width,
                    'height' => $asset->height,
                ]))
            ->values();
    }

    /** Was auf der Karte steht: das Shooting antwortet, sonst die Galerie selbst. */
    public function occursOn(): ?CarbonImmutable
    {
        return $this->shoot?->date ?? $this->event_date;
    }

    /**
     * G-14: the studio pulled this gallery back. Every guest entry point —
     * portal and api alike — closes on this, while the versions it stood on
     * stay in the database for the next publish.
     */
    public function isWithdrawn(): bool
    {
        return $this->unpublished_at !== null;
    }

    /**
     * R39: prerelease_at only means anything while nobody has published
     * yet — a version, once it exists, is the real thing to show, and a
     * stale flag left set after publish() must not resurrect the "wird
     * vorbereitet" page for it. is_external excluded for the same reason
     * draft() already refuses an external gallery explicitly (Gallery
     * ReadController): it owns no draft assets, so "being prepared" names
     * a state it can never actually be in.
     */
    public function isInPrerelease(): bool
    {
        return $this->prerelease_at !== null && $this->currentVersion === null && ! $this->is_external;
    }

    public function prereleaseSignups(): HasMany
    {
        return $this->hasMany(GalleryPrereleaseSignup::class);
    }

    /** The most recently published version, or null before this gallery's first publish() call. */
    public function currentVersion(): HasOne
    {
        return $this->hasOne(GalleryVersion::class)->latestOfMany('version');
    }

    /** Every multipart upload session (G-02) ever started into this gallery's quarantine, whatever its state. */
    public function multipartUploads(): HasMany
    {
        return $this->hasMany(MultipartUpload::class);
    }

    /**
     * Snapshots title/assets into a brand new GalleryVersion and leaves every
     * earlier one untouched — no update, no delete. The transaction only
     * wraps the read-then-insert (the same shape DocumentTemplate::
     * newVersion() uses for its own family), so two concurrent publishes
     * either serialize on it or one loses the unique (tenant_id, gallery_id,
     * version) index, never both landing "version 2".
     *
     * An external gallery (X-02) owns no assets of its own to snapshot —
     * publishing one would only ever produce an empty GalleryVersion, so
     * this refuses rather than writing a meaningless row.
     */
    public function publish(): GalleryVersion
    {
        if ($this->is_external) {
            throw new LogicException('Cannot publish an external gallery — it links to assets hosted elsewhere and owns no version history.');
        }

        return DB::transaction(function () {
            $retention = app(StudioSettingsResolver::class)->galleryDefaults()['expiryDays'];

            if ($retention !== null && $this->expires_at === null) {
                // Stamped once, on first publish. A second version does not
                // restart the clock: the client has had the photographs since
                // version one, and a re-publish to fix a caption is not a new
                // delivery.
                $this->update(['expires_at' => now()->addDays((int) $retention)]);
            }

            $next = 1 + (int) $this->versions()->max('version');

            return $this->versions()->create([
                'version' => $next,
                'title' => $this->title,
                'assets' => $this->draftAssets()->all(),
                'design' => $this->design,
                'cover_key' => $this->coverAsset?->key,
                'published_at' => now(),
            ]);
        });
    }

    public function requiresViewPassword(): bool
    {
        return $this->view_password !== null;
    }

    public function requiresDownloadPin(): bool
    {
        return $this->download_pin !== null;
    }

    public function requiresVerifiedEmail(): bool
    {
        return $this->requires_verified_email;
    }

    public function checkViewPassword(string $password): bool
    {
        return $this->view_password !== null && hash_equals($this->view_password, $password);
    }

    public function checkDownloadPin(string $pin): bool
    {
        return $this->download_pin !== null && hash_equals($this->download_pin, $pin);
    }

    /**
     * Sets the view password, or clears it given null, and always bumps
     * view_password_version — the only thing a granted claim is ever checked
     * against (GalleryAccessSession). Touches nothing else: a guest already
     * holding a download or email claim keeps it.
     */
    public function rotateViewPassword(?string $password): void
    {
        $this->forceFill([
            'view_password' => $password,
            'view_password_version' => $this->view_password_version + 1,
        ])->save();
    }

    /** Same shape as rotateViewPassword(), for the independent download gate. */
    public function rotateDownloadPin(?string $pin): void
    {
        $this->forceFill([
            'download_pin' => $pin,
            'download_pin_version' => $this->download_pin_version + 1,
        ])->save();
    }

    /**
     * Toggles whether a download requires a verified email and always bumps
     * email_verification_version — there is no secret to change here, but the
     * version is still the whole of what invalidates an already-granted
     * claim, so this follows the same rule the other two gates do.
     */
    public function rotateEmailVerification(bool $required): void
    {
        $this->forceFill([
            'requires_verified_email' => $required,
            'email_verification_version' => $this->email_verification_version + 1,
        ])->save();
    }

    /**
     * R31: the counter a draft-preview pass (GalleryPreviewPass) is checked
     * against — both the head button's short pass and "Vorschaulink
     * erzeugen"'s long one share it, so bumping it is the whole of what
     * revoking a preview link means. Called only when a new link is minted:
     * a fresh link is meant to survive across sessions, so nothing else
     * bumps it early and kills a link nobody asked to replace yet.
     */
    public function rotatePreviewPass(): void
    {
        $this->forceFill(['preview_pass_version' => $this->preview_pass_version + 1])->save();
    }

    protected static function newFactory(): Factory
    {
        return GalleryFactory::new();
    }

    /** An external gallery (X-02) links to assets hosted elsewhere and owns no set to put them in. */
    protected static function booted(): void
    {
        static::creating(function (Gallery $gallery) {
            $gallery->slug ??= GallerySlug::unique(
                $gallery->title ?? 'galerie',
                // Global, not tenant-scoped: two studios choosing the same
                // slug would collide on the same un-tenanted route.
                fn (string $candidate) => static::query()->withoutGlobalScope('tenant')->where('slug', $candidate)->exists(),
            );
        });

        static::created(function (Gallery $gallery) {
            if (! $gallery->is_external) {
                $gallery->sets()->create(['name' => 'Highlights', 'position' => 0]);
            }
        });
    }
}
