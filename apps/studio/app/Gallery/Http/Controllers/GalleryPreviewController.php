<?php

namespace App\Gallery\Http\Controllers;

use App\Gallery\Models\Gallery;
use App\Gallery\Support\GalleryPreviewPass;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * R31: one pass mechanism (GalleryPreviewPass), minted from two places in
 * Record.vue's head with two different abilities.
 *
 * button() backs the "Vorschau" button: the studio checking its own
 * in-progress work, gated the same as the pane it is standing on
 * (manageMedia). link() backs "Vorschaulink erzeugen" in the More menu: it
 * hands the draft to someone outside the studio — a couple, a second
 * shooter — which is the same judgment GalleryPolicy::publish already makes
 * for "who decides a gallery is presentable", so it uses that ability
 * instead. A fresh link always revokes whatever was handed out before it —
 * see Gallery::rotatePreviewPass().
 */
class GalleryPreviewController extends Controller
{
    public function button(Gallery $gallery, GalleryPreviewPass $pass): JsonResponse
    {
        $this->authorize('manageMedia', $gallery);

        return response()->json(['url' => $this->url($gallery, $pass->issue($gallery, GalleryPreviewPass::BUTTON_MINUTES))]);
    }

    public function link(Gallery $gallery, GalleryPreviewPass $pass): JsonResponse
    {
        $this->authorize('publish', $gallery);

        $gallery->rotatePreviewPass();

        return response()->json(['url' => $this->url($gallery, $pass->issue($gallery, GalleryPreviewPass::LINK_MINUTES))]);
    }

    private function url(Gallery $gallery, string $token): string
    {
        return $gallery->publicUrl().'?preview='.$token;
    }
}
