<?php

namespace App\Gallery\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Media\Models\MultipartUpload;
use Database\Factories\Gallery\GalleryFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use LogicException;

/**
 * A delivery gallery's draft state, plus the family every GalleryVersion
 * belongs to. shoot_id is nullable because a gallery frequently spans the
 * whole project rather than one shoot (G-05).
 *
 * title and assets here are the working copy — freely editable, never what a
 * guest is served. publish() is the only way their contents reach a
 * GalleryVersion, and once written that row never changes again; see
 * publish() for why, and G-08 (version-scoped caching) for the story that
 * relies on it.
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
        'project_id', 'shoot_id', 'title', 'assets',
        'requires_settled_balance', 'balance_settled',
        'is_external', 'external_url', 'expires_at',
    ];

    protected $attributes = [
        'assets' => '[]',
        'requires_settled_balance' => false,
        'balance_settled' => false,
        'is_external' => false,
        'requires_verified_email' => false,
        'view_password_version' => 1,
        'download_pin_version' => 1,
        'email_verification_version' => 1,
    ];

    protected function casts(): array
    {
        return [
            'assets' => 'array',
            'requires_settled_balance' => 'boolean',
            'balance_settled' => 'boolean',
            'is_external' => 'boolean',
            // Laravel hashes a plain value assigned here and leaves an
            // already-hashed one (or null) alone — exactly what rotate*()
            // below needs, without a manual Hash::make() of its own.
            'view_password_hash' => 'hashed',
            'download_pin_hash' => 'hashed',
            'requires_verified_email' => 'boolean',
            'expires_at' => 'immutable_datetime',
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

    public function versions(): HasMany
    {
        return $this->hasMany(GalleryVersion::class);
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
            $retention = config('gallery.retention_days');

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
                'assets' => $this->assets,
                'published_at' => now(),
            ]);
        });
    }

    public function requiresViewPassword(): bool
    {
        return $this->view_password_hash !== null;
    }

    public function requiresDownloadPin(): bool
    {
        return $this->download_pin_hash !== null;
    }

    public function requiresVerifiedEmail(): bool
    {
        return $this->requires_verified_email;
    }

    public function checkViewPassword(string $password): bool
    {
        return $this->view_password_hash !== null && Hash::check($password, $this->view_password_hash);
    }

    public function checkDownloadPin(string $pin): bool
    {
        return $this->download_pin_hash !== null && Hash::check($pin, $this->download_pin_hash);
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
            'view_password_hash' => $password,
            'view_password_version' => $this->view_password_version + 1,
        ])->save();
    }

    /** Same shape as rotateViewPassword(), for the independent download gate. */
    public function rotateDownloadPin(?string $pin): void
    {
        $this->forceFill([
            'download_pin_hash' => $pin,
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

    protected static function newFactory(): Factory
    {
        return GalleryFactory::new();
    }
}
