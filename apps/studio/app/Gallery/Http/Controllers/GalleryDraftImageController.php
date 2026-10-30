<?php

namespace App\Gallery\Http\Controllers;

use App\Gallery\Models\Gallery;
use App\Gallery\Support\GalleryPreviewPass;
use App\Http\Controllers\Controller;
use App\Media\Support\ImageVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * GalleryImageController's counterpart for a draft. That route addresses
 * bytes by GalleryVersion because a version's own assets never change once
 * published — the whole reason its response can claim `immutable`. A draft
 * has no version to address by, and its bytes are the opposite of
 * immutable: a picture can be replaced or pulled from the draft at any
 * moment. So this route is scoped to the Gallery itself, sits outside the
 * `gallery.pass` middleware group entirely (routes/api.php — R35: that
 * group's own password gate has no business being satisfiable by a
 * preview pass), checks its own GalleryPreviewPass on every request, and
 * answers no-store.
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
        // is_string(), not a raw query('preview'): a ?preview[]=x arrives as an
        // array, and opens(?string) exploding one would fatal into a 500 — the
        // uniform-failure rule this stage worked to establish. Treated the same
        // as no token at all, not coerced (PHP's array-to-string cast raises a
        // warning PHPUnit's own handler turns back into a fatal).
        $preview = $request->query('preview');
        abort_unless($pass->opens($gallery, is_string($preview) ? $preview : null), 404);

        $width = (int) $request->query('w');
        $format = (string) $request->query('fmt');

        abort_unless(ImageVariant::isAllowed($width, $format), 422);

        $known = $gallery->assets()->inDraft()->where('key', $key)->exists();
        abort_unless($known, 404);

        return response(ImageVariant::deliver($key, $width, $format), 200, [
            'Content-Type' => ImageVariant::mimeType($format),
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
