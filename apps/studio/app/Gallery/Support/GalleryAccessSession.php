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
