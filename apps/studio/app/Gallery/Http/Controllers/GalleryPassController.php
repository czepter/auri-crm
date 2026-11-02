<?php

namespace App\Gallery\Http\Controllers;

use App\Gallery\Models\Gallery;
use App\Gallery\Support\GallerySecretGate;
use App\Gallery\Support\GalleryViewPass;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * G-06's view gate, answered on the api surface so apps/gallery can render
 * its own gate screen and keep the guest on the fast viewer the whole way.
 *
 * The same GallerySecretGate the portal's own gate uses — one implementation
 * of "is this the password", so the two cannot drift apart. Only the
 * per-gallery limit applies here: see the $ip note on GallerySecretGate::attempt.
 *
 * $gallery arrives as a raw slug-or-public_id rather than an implicit-bound
 * model, same reasoning as GalleryPasswordController::store: a 404 from
 * route-model binding would tell a guesser "that id doesn't exist" before the uniform
 * failure below ever ran.
 */
class GalleryPassController extends Controller
{
    public function __invoke(
        Request $request,
        string $gallery,
        GallerySecretGate $gate,
        GalleryViewPass $pass,
    ): JsonResponse {
        $model = Gallery::findByReference($gallery);

        // G-07/G-14: same reasoning as GalleryPasswordController::store — a
        // 404 here would tell a garbage password apart from one aimed at a
        // withdrawn gallery, undoing the uniform failure this endpoint
        // exists for. Nulling $model routes it through the same
        // "nonexistent" branches below.
        if ($model?->isWithdrawn()) {
            $model = null;
        }

        // A gallery with no password has nothing to buy a pass with, and one
        // that does not exist must answer exactly as a wrong password does.
        if ($model !== null && ! $model->requiresViewPassword()) {
            return response()->json(['pass' => $pass->issue($model)]);
        }

        $password = $request->validate(['password' => ['required', 'string']])['password'];

        // R44: no IP here, every guest arrives as the SSR container. Keyed on
        // the resolved id so slug and public_id share one counter.
        $granted = $gate->attempt(null, $model?->id ?? $gallery, $model === null ? null : $model->checkViewPassword(...), $password);

        if (! $granted || $model === null) {
            return response()->json(['message' => __('gallery.password_wrong')], 422);
        }

        return response()->json(['pass' => $pass->issue($model)]);
    }
}
