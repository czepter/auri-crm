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

        // R38: withdrawal is the one gesture that means "stop showing this
        // to people outside" — a preview link already sitting in a couple's
        // inbox must stop opening the draft the same moment, not keep
        // working as the longest-lived credential left standing. The
        // studio's own pass dies with it too and that is correct: minting
        // a fresh one after "withdraw to rework, then preview" is a single
        // click, the same click this ability already gates.
        $gallery->rotatePreviewPass();

        AuditLogger::record(
            event: 'gallery.withdrawn',
            actor: auth('staff')->user(),
            context: [],
            subject: $gallery->public_id,
        );

        return back();
    }
}
