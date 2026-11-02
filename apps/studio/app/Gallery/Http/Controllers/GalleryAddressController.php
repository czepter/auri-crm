<?php

namespace App\Gallery\Http\Controllers;

use App\Gallery\Models\Gallery;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Where a link sent before prefixes existed — `/g/{slug}`, `/g/{public_id}`
 * or a bare `/{public_id}` — lives now. A slug shared by two galleries
 * answers 404 like a missing one rather than pointing at either.
 */
class GalleryAddressController extends Controller
{
    public function __invoke(string $reference): JsonResponse
    {
        $gallery = Gallery::query()->where('public_id', $reference)->first() ?? Gallery::findByLegacySlug($reference);

        abort_if($gallery === null || $gallery->isWithdrawn(), 404);

        return response()->json(['path' => "{$gallery->prefix}/{$gallery->slug}"]);
    }
}
