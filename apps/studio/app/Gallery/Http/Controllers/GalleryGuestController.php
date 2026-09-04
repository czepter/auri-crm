<?php

namespace App\Gallery\Http\Controllers;

use App\Gallery\Actions\ConfirmGalleryGuest;
use App\Gallery\Actions\RequestGalleryGuestCode;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryGuest;
use App\Gallery\Support\GalleryGuestPass;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Die Anmeldung eines Gastes, auf der zustandslosen api-Fläche.
 *
 * Zwei Aufrufe: einen Code anfordern, ihn einlösen. Was zurückkommt, ist ein
 * signiertes Token (GalleryGuestPass) — der Betrachter legt es in sein
 * eigenes Cookie und schickt es als Bearer mit, genau wie den Ansichtspass.
 *
 * Bremsen sitzen an der Adresse und nicht an der IP. Der Betrachter rendert
 * serverseitig; über eine IP zu zählen hieße, die ganze Hochzeitsgesellschaft
 * als einen Absender zu behandeln (R44).
 */
class GalleryGuestController extends Controller
{
    /** Drei Codes je Adresse und Stunde reichen für Vertipper und nicht für einen Angriff. */
    private const REQUESTS_PER_HOUR = 3;

    public function request(Request $request, Gallery $gallery, RequestGalleryGuestCode $issue): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['required', 'string', 'max:120'],
        ]);

        $key = 'gallery-guest:'.$gallery->public_id.':'.mb_strtolower(trim($validated['email']));

        if (RateLimiter::tooManyAttempts($key, self::REQUESTS_PER_HOUR)) {
            return response()->json(['sent' => false, 'retryAfter' => RateLimiter::availableIn($key)], 429);
        }

        RateLimiter::hit($key, 3600);
        $issue($gallery, $validated['email'], $validated['name']);

        return response()->json(['sent' => true]);
    }

    public function confirm(Request $request, Gallery $gallery, ConfirmGalleryGuest $confirm, GalleryGuestPass $pass): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required_without:token', 'nullable', 'email', 'max:255'],
            'code' => ['required_without:token', 'nullable', 'string', 'max:6'],
            'token' => ['required_without_all:email,code', 'nullable', 'string', 'max:128'],
            // Was dieser Browser anonym markiert hat, damit es mitwandert.
            'anonymous_id' => ['nullable', 'string', 'max:64'],
        ]);

        $guest = $validated['token'] ?? null
            ? $confirm->byToken($gallery, $validated['token'])
            : $confirm->byCode($gallery, (string) $validated['email'], (string) $validated['code']);

        if (! $guest instanceof GalleryGuest) {
            return response()->json(['confirmed' => false], 422);
        }

        $confirm->adopt($guest, (string) ($validated['anonymous_id'] ?? ''));

        return response()->json([
            'confirmed' => true,
            'name' => $guest->name,
            'email' => $guest->email,
            'pass' => $pass->issue($gallery, $guest),
        ]);
    }
}
