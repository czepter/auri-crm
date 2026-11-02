<?php

namespace App\Gallery\Http\Controllers;

use App\Gallery\Actions\StartZipBuild;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryGuest;
use App\Gallery\Support\GalleryDownloadPass;
use App\Gallery\Support\GalleryGuestPass;
use App\Gallery\Support\GallerySecretGate;
use App\Gallery\Support\ZipSizes;
use App\Http\Controllers\Controller;
use App\Media\Enums\ZipBuildSize;
use App\Media\Enums\ZipBuildStatus;
use App\Media\Models\ZipBuild;
use App\Media\Models\ZipBuildPart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

/**
 * Der Download für Gäste, auf der zustandslosen api-Fläche — damit der Gast
 * auf der Galerie-Adresse bleibt. Die Schritte sind die des Portals
 * (GalleryDownloadController::step()), die Adresse ist der bestätigte Gast
 * des Betrachters (GalleryGuestPass), die PIN-Freigabe ein GalleryDownloadPass.
 *
 * Nur "Alle": eine benannte Liste gehört dem angemeldeten Kunden im Portal.
 */
class GalleryDownloadOfferController extends Controller
{
    public function show(Request $request, Gallery $gallery, GalleryDownloadPass $pass, GalleryGuestPass $guests): JsonResponse
    {
        return response()->json($this->state($gallery, $this->step($request, $gallery, $pass, $guests), $this->guest($request, $gallery, $guests)));
    }

    public function pin(Request $request, string $gallery, GallerySecretGate $gate, GalleryDownloadPass $pass): JsonResponse
    {
        $model = Gallery::findByPublicIdOrSlug($gallery);

        if ($model?->isWithdrawn()) {
            $model = null;
        }

        if ($model !== null && ! $model->requiresDownloadPin()) {
            return response()->json(['pass' => $pass->issue($model)]);
        }

        $pin = $request->validate(['pin' => ['required', 'string']])['pin'];

        $granted = $gate->attempt(null, $model?->id ?? $gallery, $model === null ? null : $model->checkDownloadPin(...), $pin);

        if (! $granted || $model === null) {
            return response()->json(['message' => __('gallery.pin_wrong')], 422);
        }

        return response()->json(['pass' => $pass->issue($model)]);
    }

    public function prepare(
        Request $request,
        Gallery $gallery,
        GalleryDownloadPass $pass,
        GalleryGuestPass $guests,
        StartZipBuild $start,
    ): JsonResponse {
        $step = $this->step($request, $gallery, $pass, $guests);
        $guest = $this->guest($request, $gallery, $guests);

        abort_unless($step === 'ready' && $guest !== null, 404);

        $size = ZipBuildSize::from($request->validate([
            'size' => ['required', Rule::enum(ZipBuildSize::class)],
        ])['size']);

        $start($gallery->currentVersion, $size, null, $guest->email, GalleryDownloadController::PARTS_LIVE_HOURS);

        return response()->json($this->state($gallery, $step, $guest));
    }

    private function state(Gallery $gallery, string $step, ?GalleryGuest $guest): array
    {
        abort_if($gallery->isWithdrawn(), 404);

        $version = $gallery->currentVersion;

        abort_if($version === null, 404);

        $keys = collect($version->assets)
            ->map(fn (array|string $asset) => is_array($asset) ? ($asset['key'] ?? null) : $asset)
            ->filter()
            ->values();

        $state = [
            'step' => $step,
            'gallery' => [
                'id' => $gallery->public_id,
                'title' => $gallery->title,
                'count' => $keys->count(),
                'expiresAt' => $gallery->expires_at?->format('Y-m-d'),
            ],
            'sizes' => [],
            'builds' => [],
        ];

        if ($step !== 'ready') {
            return $state;
        }

        $state['sizes'] = ZipSizes::for($gallery, $keys->all());
        $state['builds'] = ZipBuild::query()
            ->where('gallery_version_id', $version->id)
            ->whereNull('gallery_list_id')
            ->with('parts')
            ->get()
            ->map(fn (ZipBuild $build) => [
                'size' => $build->size->value,
                'status' => $build->status->value,
                'parts' => $build->status === ZipBuildStatus::Ready
                    ? $build->parts->sortBy('part_number')->map(fn (ZipBuildPart $part) => [
                        'number' => $part->part_number,
                        'bytes' => (int) $part->byte_size,
                        'path' => $this->partPath($gallery, $part, $guest),
                    ])->values()->all()
                    : [],
            ])
            ->all();

        return $state;
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
            ['gallery' => $gallery->public_id, 'zipBuildPart' => $part->public_id, 'guestId' => $guest->identifier],
            absolute: false,
        );
    }

    private function step(Request $request, Gallery $gallery, GalleryDownloadPass $pass, GalleryGuestPass $guests): string
    {
        if ($gallery->requiresDownloadPin() && ! $pass->opens($gallery, $request->header('X-Download-Pass'))) {
            return 'pin';
        }

        return $this->guest($request, $gallery, $guests) === null ? 'email' : 'ready';
    }

    private function guest(Request $request, Gallery $gallery, GalleryGuestPass $guests): ?GalleryGuest
    {
        $identifier = $guests->identify($gallery, $request->header('X-Guest-Pass'));

        return $identifier === null ? null : GalleryGuest::query()
            ->where('gallery_id', $gallery->id)
            ->where('identifier', $identifier)
            ->whereNotNull('confirmed_at')
            ->first();
    }
}
