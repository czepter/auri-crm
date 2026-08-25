<?php

namespace App\Gallery\Support;

use App\Gallery\Models\Gallery;
use Illuminate\Support\Facades\Config;

/**
 * R31/spec decision 26: a studio-issued look at the draft, in the same
 * signed-bearer shape GalleryViewPass uses for the guest password gate —
 * one mechanism, two lifetimes. The head button in Record.vue mints one at
 * BUTTON_MINUTES; "Vorschaulink erzeugen" mints one at LINK_MINUTES and
 * bumps Gallery::preview_pass_version first (Gallery::rotatePreviewPass()),
 * so a re-generated link revokes every pass minted before it — the short
 * pass included, if one happened to still be open. Opening a revoked link
 * afterwards answers exactly as GalleryReadController answers a guest
 * carrying no pass at all: the currently-published version if one exists,
 * a 404 otherwise.
 *
 * A separate class from GalleryViewPass, not a second method on it: solving
 * the password gate proves a guest knows a secret, not that the studio
 * meant them to see pictures nobody has published yet, and the reverse
 * holds too — a preview pass opens nothing a locked gallery's password
 * protects. The leading "preview" literal folded into the signed payload
 * keeps the two token shapes structurally distinct even though both are
 * HMACs over the same app.key.
 *
 * R35: a pass this class opens() true for authorises exactly two things —
 * reading one gallery's draft (GalleryReadController) and fetching one of
 * its draft images (GalleryDraftImageController). Nothing else. It is
 * deliberately not wired into EnsureGalleryPass or any other shared gate:
 * a Critical fix round found it doing so let a draft-preview link read and
 * write comments and favourites, and read version images, on that same
 * gallery's *published*, password-protected side. A future route that
 * wants to honour this token too must call opens() itself and argue with
 * this sentence, not inherit the permission by sitting in a middleware
 * group this pass happens to satisfy.
 */
class GalleryPreviewPass
{
    /** Record.vue's head button — a self-check, gone before anyone thinks to forward it. */
    public const BUTTON_MINUTES = 30;

    /** "Vorschaulink erzeugen" — long enough to reach a couple or a second shooter; dies only on regeneration. */
    public const LINK_MINUTES = 30 * 24 * 60;

    public function issue(Gallery $gallery, int $minutes): string
    {
        $payload = implode('.', [
            'preview',
            $gallery->public_id,
            $gallery->preview_pass_version,
            now()->addMinutes($minutes)->timestamp,
        ]);

        return $payload.'.'.$this->sign($payload);
    }

    /** Same uniform-failure rule as GalleryViewPass::opens() — a stale pass and a forged one answer alike. */
    public function opens(Gallery $gallery, ?string $token): bool
    {
        if ($token === null) {
            return false;
        }

        $parts = explode('.', $token);

        if (count($parts) !== 5 || $parts[0] !== 'preview') {
            return false;
        }

        [, $galleryId, $version, $expires, $signature] = $parts;

        if (! hash_equals($this->sign("preview.{$galleryId}.{$version}.{$expires}"), $signature)) {
            return false;
        }

        return $galleryId === $gallery->public_id
            && (int) $version === $gallery->preview_pass_version
            && (int) $expires > now()->timestamp;
    }

    private function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, (string) Config::get('app.key'));
    }
}
