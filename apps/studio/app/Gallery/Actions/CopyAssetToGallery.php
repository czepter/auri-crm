<?php

namespace App\Gallery\Actions;

use App\Gallery\Models\Gallery;
use App\Media\Models\Asset;
use App\Support\LiveUpdate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Original und Derivate werden serverseitig in eigene Schlüssel unter dem
 * Präfix der Zielgalerie kopiert: Löschen der Kopie trifft das Original nie,
 * weil beide Präfixe verschieden sind. Der Schlüssel ist aus Ziel und Quelle
 * abgeleitet, damit ein abgebrochener Lauf beim Wiederholen dieselben Objekte
 * überschreibt, statt Waisen zu hinterlassen.
 */
class CopyAssetToGallery
{
    public function __construct(private readonly EnsureGalleryCover $ensureCover) {}

    public function __invoke(Gallery $target, Asset $source): void
    {
        if ($target->assets()->where('copied_from_asset_id', $source->id)->exists()) {
            return;
        }

        $disk = Storage::disk('s3');
        $id = substr(hash('sha256', "copy:{$target->id}:{$source->id}"), 0, 24);
        $base = "galleries/{$target->tenant_id}/{$target->public_id}";
        $key = "{$base}/originals/{$id}/{$source->filename}";

        if (! $disk->copy($source->key, $key)) {
            throw new RuntimeException("Unable to copy {$source->key}.");
        }

        $sets = $target->sets()->get();
        $set = $sets->first(fn ($candidate) => $candidate->name === $source->set?->name && $candidate->position === $source->set?->position)
            ?? $sets->first();

        DB::transaction(function () use ($target, $source, $disk, $id, $base, $key, $set) {
            $copy = Asset::create([
                ...$source->only(['checksum', 'status', 'filename', 'byte_size', 'width', 'height', 'taken_at', 'camera_make', 'camera_model', 'phash', 'blurhash']),
                'public_id' => $id,
                'gallery_id' => $target->id,
                'gallery_set_id' => $set->id,
                'key' => $key,
                'copied_from_asset_id' => $source->id,
            ]);

            foreach ($source->derivatives as $derivative) {
                $derivativeKey = "{$base}/derivatives/{$id}/{$derivative->width}.{$derivative->format}";

                if ($disk->copy($derivative->key, $derivativeKey)) {
                    $copy->derivatives()->create([...$derivative->only(['width', 'format', 'bytes']), 'key' => $derivativeKey]);
                }
            }

            if ($target->copySource?->cover_asset_id === $source->id) {
                $target->update([
                    'cover_asset_id' => $copy->id,
                    'cover_focal_x' => $target->copySource->cover_focal_x,
                    'cover_focal_y' => $target->copySource->cover_focal_y,
                ]);
            }
        });

        if ($target->refresh()->copyProgress() === null) {
            ($this->ensureCover)($target);
        }

        LiveUpdate::publish();
    }
}
