<?php

namespace App\Media\Http\Controllers;

use App\Gallery\Models\Gallery;
use App\Http\Controllers\Controller;
use App\Media\Data\FailedAssetData;
use App\Media\Data\MediaIngestTargetData;
use App\Media\Enums\AssetStatus;
use App\Media\Models\Asset;
use App\Support\RecordVisit;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The ingest screen itself (mockups/admin/05-medien.html) — the transfer and
 * every part of it runs client-side against MultipartUploadController; this
 * only hands the browser its target, plus G-04's "needs attention" tray of
 * whatever in this gallery is currently Failed.
 */
class MediaIngestController extends Controller
{
    public function show(Gallery $gallery): Response
    {
        $this->authorize('manageMedia', $gallery);

        RecordVisit::record('galerie', $gallery->public_id, $gallery->title ?? $gallery->project?->name ?? '');

        $failedAssets = Asset::query()
            ->where('gallery_id', $gallery->id)
            ->where('status', AssetStatus::Failed)
            ->oldest()
            ->get();

        return Inertia::render('Galleries/Ingest', [
            'target' => MediaIngestTargetData::fromGallery($gallery),
            'failedAssets' => FailedAssetData::collect($failedAssets),
        ]);
    }
}
