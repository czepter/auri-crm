<?php

namespace App\Gallery\Http\Controllers;

use App\Gallery\Models\Gallery;
use App\Gallery\Support\GalleryAccessSession;
use App\Gallery\Support\GallerySecretGate;
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

    /**
     * $gallery arrives as the raw public_id, not an implicit-bound model:
     * route-model binding 404s before this method ever runs, and a plain 404
     * here would tell a guest guessing passwords "that id doesn't exist" —
     * exactly the enumeration G-07 closes. Resolving it by hand instead lets
     * a nonexistent gallery fall through the same uniform-failure path as a
     * wrong password on a real one, all the way down to GallerySecretGate's
     * dummy hash check.
     */
    public function store(Request $request, string $gallery, GalleryAccessSession $claims, GallerySecretGate $gate): RedirectResponse
    {
        $model = Gallery::findByPublicId($gallery);
        $requiresPassword = $model === null || $model->requiresViewPassword();

        if ($requiresPassword) {
            $validated = $request->validate(['password' => ['required', 'string']]);

            $granted = $gate->attempt($request->ip(), $gallery, $model?->view_password_hash, $validated['password']);

            if (! $granted || $model === null) {
                return back()->withErrors(['password' => __('gallery.password_wrong')]);
            }
        }

        $claims->grant($model, GalleryAccessSession::VIEW, $model->view_password_version);

        return redirect("/galerien/{$model->public_id}");
    }
}
