<?php

namespace App\Gallery\Http\Controllers;

use App\Gallery\Actions\PublishGallery;
use App\Gallery\Actions\WithdrawGallery;
use App\Gallery\Models\Gallery;
use App\Gallery\Notifications\GalleryPublished;
use App\Http\Controllers\Controller;
use App\Notifications\Support\MailPreview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GalleryPublicationController extends Controller
{
    public function compose(Gallery $gallery): Response
    {
        $this->authorize('publish', $gallery);

        $contact = $gallery->case?->primaryContact();

        abort_if(blank($contact?->email), 404);

        return Inertia::render('Galleries/Send', [
            'gallery' => ['id' => $gallery->public_id, 'title' => $gallery->title ?? ''],
            'recipients' => [['name' => $contact->name, 'email' => $contact->email]],
            'mailHtml' => MailPreview::html(new GalleryPublished($gallery, '', MailPreview::MESSAGE), $contact),
        ]);
    }

    /**
     * Mails only when the compose screen posted a subject: a gallery without
     * a primary contact is published straight from Record.vue's button.
     */
    public function store(Request $request, Gallery $gallery, PublishGallery $publish): RedirectResponse
    {
        $this->authorize('publish', $gallery);

        $validated = $request->validate([
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string'],
        ]);

        $publish->announce($gallery, auth('staff')->user(), $validated['subject'] ?? null, $validated['message'] ?? null);

        $contact = $gallery->case?->primaryContact();

        if (blank($validated['subject'] ?? null) || blank($contact?->email)) {
            return back();
        }

        return redirect("/galerien/{$gallery->public_id}");
    }

    public function destroy(Gallery $gallery, WithdrawGallery $withdraw): RedirectResponse
    {
        $this->authorize('publish', $gallery);

        $withdraw($gallery, auth('staff')->user());

        return back();
    }
}
