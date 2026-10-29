<?php

namespace App\Privacy\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Gallery\Models\Gallery;
use App\Media\Enums\ZipBuildStatus;
use App\Models\User;
use App\Settings\Enums\StudioExportKind;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * #489: ein ZIP-Archiv des Studios, "Daten" oder die Originale einer Galerie.
 * Der Status ist der eines ZipBuild: building, dann ready oder failed.
 */
class StudioExport extends Model
{
    use BelongsToTenant;
    use HasPublicId;
    use Prunable;

    public const VALID_DAYS = 7;

    protected $fillable = ['kind', 'gallery_id', 'status', 'error', 'path', 'size', 'expires_at', 'requested_by_id'];

    protected function casts(): array
    {
        return [
            'kind' => StudioExportKind::class,
            'status' => ZipBuildStatus::class,
            'size' => 'integer',
            'expires_at' => 'immutable_datetime',
        ];
    }

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    /** Ready und noch nicht abgelaufen: nur so ein Archiv wird ausgeliefert. */
    public function isDownloadable(): bool
    {
        return $this->status === ZipBuildStatus::Ready
            && $this->path !== null
            && $this->expires_at?->isFuture() === true;
    }

    /** @return array{id: string, status: string, size: int|null, expiresAt: string|null, url: string|null} */
    public function asRow(): array
    {
        return [
            'id' => $this->public_id,
            'status' => $this->status->value,
            'size' => $this->size,
            'expiresAt' => $this->expires_at?->toIso8601String(),
            'url' => $this->isDownloadable()
                ? URL::temporarySignedRoute('settings.export.archive.download', $this->expires_at, ['studioExport' => $this->public_id])
                : null,
        ];
    }

    public function prunable(): Builder
    {
        return static::where('expires_at', '<', now());
    }

    protected function pruning(): void
    {
        if ($this->path !== null) {
            Storage::disk('s3')->delete($this->path);
        }
    }
}
