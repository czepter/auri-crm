<?php

namespace App\Gallery\Http\Controllers;

use App\Gallery\Models\Gallery;
use App\Gallery\Support\GalleryPreviewPass;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * R31: one pass mechanism (GalleryPreviewPass), minted from two places in
 * Record.vue's head with two different abilities.
 *
 * button() backs the "Vorschau" button: the studio checking its own
 * in-progress work, gated the same as the pane it is standing on
 * (manageMedia). Like ContactController::previewPortal(), when posted as a
 * browser form into a second tab (target="_blank"), this answers with a 302
 * redirect that tab follows natively to the gallery surface, preventing popup
 * blockers and transient user-activation loss from stalling on about:blank.
 * When called with an Accept: application/json header, it answers with the
 * JSON URL payload.
 *
 * link() backs "Vorschaulink erzeugen" in the More menu: it hands the draft to
 * someone outside the studio — a couple, a second shooter — which is the same
 * judgment GalleryPolicy::publish already makes for "who decides a gallery is
 * presentable", so it uses that ability instead. A fresh link always revokes
 * whatever was handed out before it — see Gallery::rotatePreviewPass().
 */
class GalleryPreviewController extends Controller
{
    public function button(Gallery $gallery, GalleryPreviewPass $pass): JsonResponse|SymfonyResponse
    {
        $this->authorize('manageMedia', $gallery);

        $url = $this->url($gallery, $pass->issue($gallery, GalleryPreviewPass::BUTTON_MINUTES));

        if (request()->expectsJson()) {
            return response()->json(['url' => $url]);
        }

        return Inertia::location($url);
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
