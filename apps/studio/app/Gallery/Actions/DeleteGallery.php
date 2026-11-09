<?php

namespace App\Gallery\Actions;

use App\Crm\Models\InternalNote;
use App\Gallery\Jobs\PurgeGalleryObjects;
use App\Gallery\Models\AssetComment;
use App\Gallery\Models\AssetFavorite;
use App\Gallery\Models\Gallery;
use App\Media\Models\Asset;
use App\Media\Models\AssetDerivative;
use App\Media\Models\ZipBuild;
use App\Media\Models\ZipBuildPart;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Hart, wie die Spezifikation (#24) es will: Favoriten und Kommentare gehen
 * zuerst als Datei in den Speicher, das Projekt bekommt eine Notiz mit dem
 * Pfad, danach fallen die Zeilen per Fremdschlüssel. Die Objekte räumt
 * PurgeGalleryObjects weg, nicht diese Anfrage.
 */
class DeleteGallery
{
    public function __invoke(Gallery $gallery, ?User $actor): void
    {
        $path = "exports/galleries/{$gallery->public_id}.json";

        Storage::disk('s3')->put($path, json_encode($this->export($gallery), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $prefix = "galleries/{$gallery->tenant_id}/{$gallery->public_id}/";
        $strays = $this->keys($gallery)->reject(fn (string $key) => str_starts_with($key, $prefix))->values()->all();

        DB::transaction(function () use ($gallery, $actor, $path) {
            if ($gallery->case_id !== null) {
                InternalNote::create([
                    'case_id' => $gallery->case_id,
                    'author_id' => $actor?->id,
                    'body' => __('gallery.deleted_note', ['title' => $gallery->title ?? '', 'path' => $path]),
                ]);
            }

            AuditLogger::record(
                event: 'gallery.deleted',
                actor: $actor,
                context: ['title' => $gallery->title, 'export' => $path],
                subject: $gallery->public_id,
                projectId: $gallery->case_id,
            );

            $gallery->delete();
        });

        PurgeGalleryObjects::dispatch($prefix, $strays);
    }

    /** @return array<string, mixed> */
    private function export(Gallery $gallery): array
    {
        $versions = $gallery->versions()->pluck('id');

        return [
            'gallery' => $gallery->public_id,
            'title' => $gallery->title,
            'favorites' => AssetFavorite::query()->whereIn('gallery_version_id', $versions)->with('contact')->get()
                ->map(fn (AssetFavorite $row) => [
                    'file' => basename($row->asset_key),
                    'by' => $row->contact?->name,
                    'at' => $row->created_at?->toIso8601String(),
                ])->all(),
            'comments' => AssetComment::query()->whereIn('gallery_version_id', $versions)->with('contact')->get()
                ->map(fn (AssetComment $row) => [
                    'file' => basename($row->asset_key),
                    'by' => $row->contact?->name,
                    'body' => $row->body,
                    'at' => $row->created_at?->toIso8601String(),
                ])->all(),
        ];
    }

    /** Alles, was an der Galerie hängt und einen Speicherschlüssel trägt, auch wo er nicht unter ihrem Präfix liegt. */
    private function keys(Gallery $gallery): Collection
    {
        $assets = Asset::query()->where('gallery_id', $gallery->id);
        $builds = ZipBuild::query()->whereIn('gallery_version_id', $gallery->versions()->select('id'))->select('id');

        return collect()
            ->merge((clone $assets)->whereNotNull('key')->pluck('key'))
            ->merge(AssetDerivative::query()->whereIn('asset_id', (clone $assets)->select('id'))->pluck('key'))
            ->merge(ZipBuildPart::query()->whereIn('zip_build_id', $builds)->pluck('key'))
            ->merge($gallery->multipartUploads()->pluck('key'))
            // Tabelle statt Modell: Privacy liegt in der Schichtung rechts von Gallery. Das Archiv fällt mit der Zeile, seine Datei nicht.
            ->merge(DB::table('studio_exports')->where('gallery_id', $gallery->id)->whereNotNull('path')->pluck('path'))
            ->filter()
            ->unique();
    }
}
