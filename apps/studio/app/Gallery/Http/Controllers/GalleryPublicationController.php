<?php

namespace App\Gallery\Http\Controllers;

use App\Gallery\Actions\PublishGallery;
use App\Gallery\Models\Gallery;
use App\Http\Controllers\Controller;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;

class GalleryPublicationController extends Controller
{
    public function store(Gallery $gallery, PublishGallery $publish): RedirectResponse
    {
        $this->authorize('publish', $gallery);

        $version = $publish($gallery);

        AuditLogger::record(
            event: 'gallery.published',
            actor: auth('staff')->user(),
            context: ['version' => $version?->version],
            subject: $gallery->public_id,
        );

        return back();
    }

    public function destroy(Gallery $gallery): RedirectResponse
    {
        $this->authorize('publish', $gallery);

        $gallery->forceFill(['unpublished_at' => now()])->save();

        AuditLogger::record(
            event: 'gallery.withdrawn',
            actor: auth('staff')->user(),
            context: [],
            subject: $gallery->public_id,
        );

        return back();
    }
}
