<?php

namespace App\Gallery\Http\Controllers;

use App\Gallery\Actions\SetAssetHidden;
use App\Gallery\Models\AssetHide;
use App\Gallery\Models\Gallery;
use App\Gallery\Support\GalleryVisitor;
use App\Http\Controllers\Controller;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Ausblenden: der Kunde auf dem Portal, das Studio zum Nachsehen und
 * Zurückholen. Für Gäste gibt es keinen Weg hierher.
 */
class AssetHideController extends Controller
{
    public function toggle(Request $request, Gallery $gallery, GalleryVisitor $visitor, SetAssetHidden $set): JsonResponse
    {
        $contact = $visitor->client($gallery);
        abort_if($contact === null, 404);

        $key = $request->validate(['key' => ['required', 'string']])['key'];
        $version = $gallery->currentVersion;
        abort_if($version === null || ! $version->sees($key, true), 404);

        $hidden = ! AssetHide::query()
            ->where(['gallery_id' => $gallery->id, 'asset_key' => $key, 'contact_id' => $contact->id])
            ->exists();

        $set($gallery, $contact, $key, $hidden);

        return response()->json(['hidden' => $hidden]);
    }

    public function index(Gallery $gallery): JsonResponse
    {
        $this->authorize('manageMedia', $gallery);

        return response()->json([
            'hidden' => AssetHide::query()
                ->where('gallery_id', $gallery->id)
                ->with('contact')
                ->oldest()
                ->get()
                ->map(fn (AssetHide $hide) => [
                    'id' => $hide->public_id,
                    'key' => $hide->asset_key,
                    'contact' => $hide->contact->name,
                    'hiddenAt' => $hide->created_at->toIso8601String(),
                ])
                ->values(),
        ]);
    }

    public function restore(Gallery $gallery, AssetHide $hide, SetAssetHidden $set): RedirectResponse
    {
        $this->authorize('manageMedia', $gallery);
        abort_unless($hide->gallery_id === $gallery->id, 404);

        $set($gallery, $hide->contact, $hide->asset_key, false);

        AuditLogger::record(
            event: 'gallery.asset_restored',
            actor: auth('staff')->user(),
            context: ['key' => $hide->asset_key, 'contact' => $hide->contact->public_id],
            subject: $gallery->public_id,
        );

        return back();
    }
}
