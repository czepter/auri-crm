<?php

namespace App\Gallery\Http\Controllers;

use App\Gallery\Actions\PublishGallery;
use App\Gallery\Actions\WithdrawGallery;
use App\Gallery\Models\Gallery;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GalleryPublicationController extends Controller
{
    /**
     * Mails only when the send dialog posted a subject: a gallery without
     * a primary contact is published straight from Record.vue's button.
     */
    public function store(Request $request, Gallery $gallery, PublishGallery $publish): RedirectResponse
    {
        $this->authorize('publish', $gallery);

        $validated = $request->validate([
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string'],
            'include_password' => ['sometimes', 'boolean'],
        ]);

        $publish->announce($gallery, auth('staff')->user(), $validated['subject'] ?? null, $validated['message'] ?? null, $request->boolean('include_password'));

        return back();
    }

    public function destroy(Gallery $gallery, WithdrawGallery $withdraw): RedirectResponse
    {
        $this->authorize('publish', $gallery);

        $withdraw($gallery, auth('staff')->user());

        return back();
    }
}
