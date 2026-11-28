<?php

namespace App\Gallery\Http\Controllers;

use App\Gallery\Actions\StartZipBuild;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryDownloadLink;
use App\Gallery\Models\GalleryGuest;
use App\Gallery\Support\GallerySecretGate;
use App\Gallery\Support\GalleryViewPass;
use App\Http\Controllers\Controller;
use App\Media\Enums\ZipBuildSize;
use App\Media\Enums\ZipBuildStatus;
use App\Media\Models\ZipBuildPart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

/**
 * Der Download für Gäste, auf der zustandslosen api-Fläche — damit der Gast
 * auf der Galerie-Adresse bleibt. Zwei Aufrufe: die Adresse nennen und den
 * Bau anstoßen (store), und mit dem Token aus der Mail die Teile holen (show).
 * Dass der Link an die genannte Adresse geht, ist der Nachweis; einen Code
 * gibt es hier nicht.
 *
 * Nur "Alle": eine benannte Liste gehört dem angemeldeten Kunden im Portal.
 */
class GalleryDownloadOfferController extends Controller
{
    /** Wie beim Anmeldecode (GalleryGuestController): genug für Vertipper, nicht für einen Angriff. */
    private const REQUESTS_PER_HOUR = 3;

    /** Ein Galerie-Link würde sonst beliebig viele Mails und Bauten auslösen. */
    private const REQUESTS_PER_GALLERY_HOUR = 200;

    public function store(Request $request, Gallery $gallery, GallerySecretGate $gate, StartZipBuild $start): JsonResponse
    {
        abort_if($gallery->isWithdrawn() || $gallery->currentVersion === null, 404);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'size' => ['required', Rule::enum(ZipBuildSize::class)],
            'pin' => [Rule::requiredIf($gallery->requiresDownloadPin()), 'nullable', 'string'],
        ]);

        // Eigener Zähler: ein Erfolg am Ansichtspasswort, das jeder Gast kennt,
        // setzte sonst den der PIN zurück.
        if ($gallery->requiresDownloadPin()
            && ! $gate->attempt(null, 'pin:'.$gallery->id, $gallery->checkDownloadPin(...), (string) $validated['pin'])) {
            return response()->json(['message' => __('gallery.pin_wrong'), 'errors' => ['pin' => [__('gallery.pin_wrong')]]], 422);
        }

        $key = 'gallery-download:'.$gallery->public_id.':'.mb_strtolower(trim($validated['email']));
        $galleryKey = 'gallery-download-gallery:'.$gallery->public_id;

        foreach ([[$key, self::REQUESTS_PER_HOUR], [$galleryKey, self::REQUESTS_PER_GALLERY_HOUR]] as [$limit, $max]) {
            if (RateLimiter::tooManyAttempts($limit, $max)) {
                return response()->json(['sent' => false, 'retryAfter' => RateLimiter::availableIn($limit)], 429);
            }
        }

        RateLimiter::hit($key, 3600);
        RateLimiter::hit($galleryKey, 3600);

        $start($gallery->currentVersion, ZipBuildSize::from($validated['size']), null, $validated['email'], GalleryDownloadController::PARTS_LIVE_HOURS, guests: true);

        return response()->json(['sent' => true], 202);
    }

    public function show(Gallery $gallery, string $token, GalleryViewPass $view): JsonResponse
    {
        $link = GalleryDownloadLink::forToken($token);
        $build = $link?->zipBuild;
        $guest = $link?->guest;

        abort_if($gallery->isWithdrawn(), 404);
        abort_unless($link !== null && $link->gallery_id === $gallery->id && $build !== null && $guest !== null, 404);
        abort_unless($build->status === ZipBuildStatus::Ready && $build->parts()->exists(), 404);

        $version = $gallery->currentVersion;

        abort_if($version === null, 404);

        $keys = collect($version->assetsFor(false))
            ->map(fn (array|string $asset) => is_array($asset) ? ($asset['key'] ?? null) : $asset)
            ->filter()
            ->values();

        // Das Titelbild hinter der Seite. Eine gesperrte Galerie zeigt
        // auch auf der Galerieseite kein Bild.
        $coverKey = $gallery->view_mode === 'locked' ? null : ($version->cover_key ?? $keys->first());

        $parts = $build->parts()->orderBy('part_number')->get();

        return response()->json([
            'gallery' => [
                'id' => $gallery->public_id,
                'title' => $gallery->title,
                'count' => $keys->count(),
                'cover' => $coverKey === null ? null : [
                    ...$gallery->coverFor($coverKey),
                    'version' => $version->public_id,
                    'pass' => $gallery->requiresViewPassword() ? $view->issueForImages($gallery) : null,
                ],
            ],
            'size' => $build->size->value,
            'bytes' => (int) $parts->sum('byte_size'),
            'parts' => $parts->map(fn (ZipBuildPart $part) => [
                'number' => $part->part_number,
                'bytes' => (int) $part->byte_size,
                'path' => $this->partPath($gallery, $part, $guest),
            ])->all(),
        ]);
    }

    /**
     * Relativ signiert: dieser Prozess sieht sich unter dem Namen des
     * Containers, der Gast unter der öffentlichen Adresse — den Host setzt
     * der Betrachter davor.
     */
    private function partPath(Gallery $gallery, ZipBuildPart $part, GalleryGuest $guest): string
    {
        return URL::temporarySignedRoute(
            'api.galleries.download.part',
            now()->addHours(GalleryDownloadController::PARTS_LIVE_HOURS),
            ['gallery' => $gallery->public_id, 'zipBuildPart' => $part->public_id, 'guestId' => $guest->identifier, 'pv' => $gallery->download_pin_version, 'vv' => $gallery->view_password_version],
            absolute: false,
        );
    }
}
