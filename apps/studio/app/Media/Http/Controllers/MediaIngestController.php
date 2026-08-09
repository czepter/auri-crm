<?php

namespace App\Media\Http\Controllers;

use App\Gallery\Models\Gallery;
use App\Http\Controllers\Controller;
use App\Media\Data\MediaIngestTargetData;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The ingest screen itself (mockups/admin/05-medien.html) — the transfer and
 * every part of it runs client-side against MultipartUploadController; this
 * only hands the browser its target.
 */
class MediaIngestController extends Controller
{
    public function show(Gallery $gallery): Response
    {
        $this->authorize('manageMedia', $gallery);

        return Inertia::render('Galleries/Ingest', [
            'target' => MediaIngestTargetData::fromGallery($gallery),
        ]);
    }
}
