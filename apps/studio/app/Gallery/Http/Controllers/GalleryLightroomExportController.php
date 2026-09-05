<?php

namespace App\Gallery\Http\Controllers;

use App\Gallery\Models\AssetFavorite;
use App\Gallery\Models\Gallery;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * G-12: the studio side of proofing — a plain-text filename list of every
 * asset a client favorited in the gallery's currently-published version,
 * one filename per line. That's the whole format: Lightroom's own filename-
 * based selection workflows (Library filter, "Select by filenames") consume
 * exactly this, no XML/LRCAT/XMP sidecar needed for a bare selection.
 *
 * asset_key is the full storage path (originals/{assetPublicId}/{filename},
 * IngestCompletedUpload's own doc comment) — basename() recovers the
 * original filename Lightroom's catalog still has on disk, without a
 * separate column to keep in sync.
 *
 * No mockup shows this button yet, and the Bilder tab (Galleries/Record.vue)
 * does not link to it either. This ships as a route+action a future screen
 * can link to, same shape as contracts.download.
 */
class GalleryLightroomExportController extends Controller
{
    public function __invoke(Gallery $gallery): Response
    {
        $this->authorize('manageMedia', $gallery);

        $version = $gallery->currentVersion;
        abort_unless($version, 404);

        $filenames = AssetFavorite::query()
            ->where('gallery_version_id', $version->id)
            ->pluck('asset_key')
            ->map(fn (string $key) => basename($key))
            ->unique()
            ->sort()
            ->values();

        $filename = Str::slug($gallery->title !== '' ? $gallery->title : 'galerie').'-auswahl.txt';

        return response($filenames->implode("\n"), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
