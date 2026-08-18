<?php

namespace App\Gallery\Support;

use App\Crm\Models\Contact;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryList;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Who is looking at this gallery.
 *
 * One gallery, two audiences. The couple it belongs to, and the eighty
 * people they sent the link to. They see the same photographs and almost
 * nothing else in common: the couple own the link, the gates in front of it
 * and their own selections; a guest owns a heart and whatever they type into
 * the comment box.
 *
 * The distinction was written out four separate times — once in each
 * controller that needed it — which is three chances for them to disagree
 * about who counts as the client. It lives here now, and every screen and
 * endpoint asks the same question in the same words.
 *
 * Both identities ride on the portal surface's own session cookie, which is
 * also where GalleryAccessSession keeps the view claim. That is the whole of
 * the auth system this needs, and the reason the gallery had to come off the
 * api surface to get it: a client is an authenticated Contact
 * (auth('portal'), a magic link) who is on the gallery's own booking; a
 * guest is a session with no Contact behind it.
 */
class GalleryVisitor
{
    private const GUEST_KEY = 'gallery_guest_id';

    public function __construct(private readonly Request $request) {}

    /**
     * The client this gallery belongs to, or null.
     *
     * Checked against the booking rather than taken from the guard alone:
     * somebody signed in to their own portal who then follows a link a
     * friend forwarded is a guest on that gallery, and has to read as one.
     */
    public function client(Gallery $gallery): ?Contact
    {
        $contact = auth('portal')->user();

        if ($contact === null || $gallery->project_id === null) {
            return null;
        }

        return $contact->visibleProjects()->whereKey($gallery->project_id)->exists()
            ? $contact
            : null;
    }

    public function isClient(Gallery $gallery): bool
    {
        return $this->client($gallery) !== null;
    }

    /**
     * This visitor's own id, for the length of their session and no longer.
     *
     * A client has one too — they are also somebody with a browser — but
     * their marks are a GalleryList owned by the Contact (P-06, #225),
     * because a value that dies with a cookie cannot carry "Eure Auswahl"
     * across two devices. This is what a guest's heart and comment hang off.
     */
    public function guestId(): string
    {
        $existing = $this->request->session()->get(self::GUEST_KEY);

        if ($existing !== null) {
            return $existing;
        }

        $this->request->session()->put(self::GUEST_KEY, $minted = bin2hex(random_bytes(16)));

        return $minted;
    }

    /**
     * The client's own selections on this gallery, newest-named last and the
     * unnamed heart first. Empty for a guest — a named list belongs to the
     * client, and the grid's filter bar draws the same line.
     *
     * @return Collection<int, GalleryList>
     */
    public function lists(Gallery $gallery): Collection
    {
        $contact = $this->client($gallery);

        if ($contact === null) {
            return collect();
        }

        return GalleryList::query()
            ->where('gallery_id', $gallery->id)
            ->where('contact_id', $contact->id)
            ->with('items')
            ->get()
            ->sortBy(fn (GalleryList $list) => [$list->name === null ? 0 : 1, $list->id])
            ->values();
    }
}
