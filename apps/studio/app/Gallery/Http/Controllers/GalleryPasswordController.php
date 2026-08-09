<?php

namespace App\Gallery\Http\Controllers;

use App\Gallery\Models\Gallery;
use App\Gallery\Support\GalleryAccessSession;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The view gate — mockup 08. Its own GET is the gallery link a studio's
 * client shares, so it renders one of two states itself rather than being
 * wrapped by EnsureGalleryGate (which would have nowhere else to redirect a
 * failing request to but here, forever). Once granted, the claim
 * (GalleryAccessSession::VIEW) is what every other gallery route behind
 * `gallery.gate:view` checks — G-08's grid and G-09's lightbox included, once
 * those stories give this route something more than a placeholder to show.
 */
class GalleryPasswordController extends Controller
{
    public function show(Gallery $gallery, GalleryAccessSession $claims): Response
    {
        $unlocked = ! $gallery->requiresViewPassword()
            || $claims->has($gallery, GalleryAccessSession::VIEW, $gallery->view_password_version);

        return Inertia::render('Gallery/Show', [
            'gallery' => [
                'id' => $gallery->public_id,
                'title' => $gallery->title,
                'date' => $gallery->shoot?->date?->format('Y-m-d'),
                'venue' => $gallery->shoot?->venue(),
            ],
            'unlocked' => $unlocked,
        ]);
    }

    public function store(Request $request, Gallery $gallery, GalleryAccessSession $claims): RedirectResponse
    {
        if ($gallery->requiresViewPassword()) {
            $validated = $request->validate(['password' => ['required', 'string']]);

            if (! $gallery->checkViewPassword($validated['password'])) {
                return back()->withErrors(['password' => __('gallery.password_wrong')]);
            }
        }

        $claims->grant($gallery, GalleryAccessSession::VIEW, $gallery->view_password_version);

        return redirect("/galerien/{$gallery->public_id}");
    }
}
