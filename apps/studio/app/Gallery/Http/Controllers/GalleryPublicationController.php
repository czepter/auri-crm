<?php

namespace App\Gallery\Http\Controllers;

use App\Gallery\Actions\PublishGallery;
use App\Gallery\Models\Gallery;
use App\Gallery\Notifications\GalleryPublished;
use App\Http\Controllers\Controller;
use App\Notifications\NotificationService;
use App\Notifications\Support\MailPreview;
use App\Settings\Support\StudioSettingsResolver;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GalleryPublicationController extends Controller
{
    public function compose(Gallery $gallery, StudioSettingsResolver $settings): Response
    {
        $this->authorize('publish', $gallery);

        $contact = $gallery->case?->primaryContact();

        abort_if(blank($contact?->email), 404);

        return Inertia::render('Galleries/Send', [
            'gallery' => ['id' => $gallery->public_id, 'title' => $gallery->title ?? ''],
            'recipients' => [['name' => $contact->name, 'email' => $contact->email]],
            'mailHtml' => MailPreview::html(new GalleryPublished($gallery, '', MailPreview::MESSAGE, $settings), $contact),
        ]);
    }

    /**
     * Mails only when the compose screen posted a subject: a gallery without
     * a primary contact is published straight from Record.vue's button.
     */
    public function store(Request $request, Gallery $gallery, PublishGallery $publish, NotificationService $notifications, StudioSettingsResolver $settings): RedirectResponse
    {
        $this->authorize('publish', $gallery);

        $validated = $request->validate([
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string'],
        ]);

        $version = $publish($gallery);

        AuditLogger::record(
            event: 'gallery.published',
            actor: auth('staff')->user(),
            context: ['version' => $version?->version],
            subject: $gallery->public_id,
        );

        $contact = $gallery->case?->primaryContact();

        if (blank($validated['subject'] ?? null) || blank($contact?->email)) {
            return back();
        }

        $notifications->send($contact, new GalleryPublished($gallery, $validated['subject'], $validated['message'] ?? null, $settings));

        return redirect("/galerien/{$gallery->public_id}");
    }

    public function destroy(Gallery $gallery): RedirectResponse
    {
        $this->authorize('publish', $gallery);

        $gallery->forceFill(['unpublished_at' => now()])->save();

        // R38: withdrawal is the one gesture that means "stop showing this
        // to people outside" — a preview link already sitting in a couple's
        // inbox must stop opening the draft the same moment, not keep
        // working as the longest-lived credential left standing. The
        // studio's own pass dies with it too and that is correct: minting
        // a fresh one after "withdraw to rework, then preview" is a single
        // click, the same click this ability already gates.
        $gallery->rotatePreviewPass();

        AuditLogger::record(
            event: 'gallery.withdrawn',
            actor: auth('staff')->user(),
            context: [],
            subject: $gallery->public_id,
        );

        return back();
    }
}
