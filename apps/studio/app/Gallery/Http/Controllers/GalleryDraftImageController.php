<?php

namespace App\Gallery\Http\Controllers;

use App\Gallery\Models\Gallery;
use App\Gallery\Support\GalleryPreviewPass;
use App\Gallery\Support\ImageVariant;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * GalleryImageController's counterpart for a draft. That route addresses
 * bytes by GalleryVersion because a version's own assets never change once
 * published — the whole reason its response can claim `immutable`. A draft
 * has no version to address by, and its bytes are the opposite of
 * immutable: a picture can be replaced or pulled from the draft at any
 * moment. So this route is scoped to the Gallery itself, checks its own
 * GalleryPreviewPass on every request rather than deferring to
 * EnsureGalleryPass's password gate, and answers no-store.
 *
 * $key must belong to this gallery's own draft — not merely exist on the
 * disk — for the same reason GalleryImageController pins $key to the
 * version's own asset array: implicit route binding resolves {gallery}
 * tenant-globally, so nothing upstream of this method has already checked
 * that $key is one this specific gallery owns.
 */
class GalleryDraftImageController extends Controller
{
    public function __invoke(Request $request, Gallery $gallery, string $key, GalleryPreviewPass $pass): Response
    {
        abort_unless($pass->opens($gallery, $request->query('preview')), 404);

        $width = (int) $request->query('w');
        $format = (string) $request->query('fmt');

        abort_unless(ImageVariant::isAllowed($width, $format), 422);

        $known = $gallery->assetRows()->inDraft()->where('key', $key)->exists();
        abort_unless($known, 404);

        return response(ImageVariant::deliver($key, $width, $format), 200, [
            'Content-Type' => ImageVariant::mimeType($format),
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
