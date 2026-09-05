<?php

namespace App\Gallery\Support;

use App\Gallery\Models\Gallery;
use Illuminate\Http\Request;

/**
 * The per-visitor claim each of G-06's three gates grants once satisfied,
 * held in the portal surface's own session — the same encrypted, signed
 * cookie every other client-facing claim in this app already rides on (see
 * config/session.php's per-surface cookie name; no bespoke crypto needed on
 * top of it).
 *
 * A claim carries the gate's version at the moment it was granted. Checking
 * it against the gallery's *current* version is what makes a rotation
 * invalidate exactly the claims minted before it: the other two gates carry
 * their own version and are never touched by one gate's rotate*() call.
 */
class GalleryAccessSession
{
    public const VIEW = 'view';

    public const DOWNLOAD = 'download';

    public const EMAIL = 'email';

    public function __construct(private readonly Request $request) {}

    public function grant(Gallery $gallery, string $gate, int $version, array $extra = []): void
    {
        $this->request->session()->put($this->key($gallery, $gate), ['version' => $version, ...$extra]);
    }

    public function has(Gallery $gallery, string $gate, int $currentVersion): bool
    {
        $claim = $this->request->session()->get($this->key($gallery, $gate));

        return $claim !== null && ($claim['version'] ?? null) === $currentVersion;
    }

    /**
     * P-08 (#227): the one question "may this visitor see the pictures?",
     * asked identically by the middleware and by the screen itself.
     *
     * The view password exists so a couple can hand a link to eighty guests
     * and still decide who gets in. It was never meant to stand between the
     * couple and their own photographs — they proved an inbox to get here
     * (A-02), which is a stronger claim than a shared word. So a signed-in
     * contact on the gallery's own project passes without it.
     */
    public function viewGranted(Gallery $gallery): bool
    {
        return ! $gallery->requiresViewPassword()
            || $this->has($gallery, self::VIEW, $gallery->view_password_version)
            || $this->belongsToSignedInClient($gallery);
    }

    private function belongsToSignedInClient(Gallery $gallery): bool
    {
        $contact = auth('portal')->user();

        return $contact !== null
            && $gallery->project_id !== null
            && $contact->visibleProjects()->whereKey($gallery->project_id)->exists();
    }

    /** The address an already-granted email claim verified, or null. */
    public function verifiedEmail(Gallery $gallery): ?string
    {
        return $this->request->session()->get($this->key($gallery, self::EMAIL))['email'] ?? null;
    }

    private function key(Gallery $gallery, string $gate): string
    {
        return "gallery_access.{$gallery->public_id}.{$gate}";
    }
}
