<?php

namespace App\Gallery\Actions;

use App\Gallery\Models\Gallery;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Struktur, Einstellungen und Bilder. Die Bilder kommen als eigene Objekte
 * und Zeilen (CopyGalleryAssets), nie als zweite Zeile auf dieselben
 * Schlüssel: die würden einander beim Löschen die Dateien nehmen. Passwörter
 * und Ablauf gehören zur Auslieferung und beginnen bei der Kopie neu.
 */
class DuplicateGallery
{
    public function __construct(private readonly CopyGalleryAssets $copyAssets) {}

    /** @param  list<string>|null  $assetIds  public_ids der zu kopierenden Bilder, null für alle */
    public function __invoke(Gallery $source, ?User $actor, ?string $suffix = null, ?array $assetIds = null): Gallery
    {
        $copy = DB::transaction(function () use ($source, $actor, $suffix, $assetIds) {
            $copy = Gallery::create([
                ...$source->only([
                    'case_id', 'shoot_id', 'design', 'info', 'event_date',
                    'download_rule', 'download_invoice_id', 'download_sizes', 'view_mode', 'watermark_text', 'expiry_reminder',
                    'lists_enabled', 'comments_enabled', 'permissions', 'intro_enabled', 'gallery_preset_id',
                ]),
                'title' => trim(($source->title ?? '').' '.($suffix ?? __('gallery.copy_suffix'))),
                'copy_source_id' => $source->id,
                'copy_asset_ids' => $assetIds,
                'copy_total' => $source->assets()->inDraft()
                    ->when($assetIds !== null, fn ($query) => $query->whereIn('public_id', $assetIds))
                    ->count(),
            ]);

            $copy->sets()->delete();

            foreach ($source->sets as $set) {
                $copy->sets()->create($set->only(['name', 'position', 'sort_key', 'sort_dir', 'access']));
            }

            AuditLogger::record(
                event: 'gallery.duplicated',
                actor: $actor,
                context: ['from' => $source->public_id],
                subject: $copy->public_id,
            );

            return $copy;
        });

        ($this->copyAssets)($copy);

        return $copy;
    }
}
