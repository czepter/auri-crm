<?php

namespace App\Gallery\Http\Controllers;

use App\Gallery\Actions\DeleteGallerySet;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryGridPref;
use App\Gallery\Models\GallerySet;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GallerySetController extends Controller
{
    public function store(Request $request, Gallery $gallery): RedirectResponse
    {
        $this->authorize('manageMedia', $gallery);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('gallery_sets', 'name')
                ->where('gallery_id', $gallery->id)],
        ]);

        $gallery->sets()->create([
            'name' => $validated['name'],
            'position' => 1 + (int) $gallery->sets()->max('position'),
        ]);

        return back();
    }

    public function update(Request $request, Gallery $gallery, GallerySet $gallerySet): RedirectResponse
    {
        $this->authorize('manageMedia', $gallery);
        abort_unless($gallerySet->gallery_id === $gallery->id, 404);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:80', Rule::unique('gallery_sets', 'name')
                ->where('gallery_id', $gallery->id)->ignore($gallerySet->id)],
            'sort_key' => ['sometimes', Rule::in(['taken_at', 'filename'])],
            'sort_dir' => ['sometimes', Rule::in(['asc', 'desc'])],
        ]);

        $gallerySet->update($validated);

        return back();
    }

    public function reorder(Request $request, Gallery $gallery): RedirectResponse
    {
        $this->authorize('manageMedia', $gallery);

        $validated = $request->validate([
            'order' => ['required', 'list', 'size:'.$gallery->sets()->count()],
            'order.*' => ['required', 'string', 'distinct', Rule::in($gallery->sets()->pluck('public_id'))],
        ]);

        foreach ($validated['order'] as $position => $publicId) {
            $gallery->sets()->where('public_id', $publicId)->update(['position' => $position]);
        }

        return back();
    }

    public function density(Request $request, Gallery $gallery, GallerySet $gallerySet): RedirectResponse
    {
        $this->authorize('manageMedia', $gallery);
        abort_unless($gallerySet->gallery_id === $gallery->id, 404);

        $validated = $request->validate([
            'density' => ['required', Rule::in(['small', 'medium', 'large'])],
        ]);

        GalleryGridPref::query()->updateOrCreate(
            ['user_id' => auth('staff')->id(), 'gallery_set_id' => $gallerySet->id],
            ['density' => $validated['density']],
        );

        return back();
    }

    public function destroy(Request $request, Gallery $gallery, GallerySet $gallerySet, DeleteGallerySet $delete): RedirectResponse
    {
        $this->authorize('manageMedia', $gallery);
        abort_unless($gallerySet->gallery_id === $gallery->id, 404);

        if ($gallery->sets()->count() === 1) {
            throw ValidationException::withMessages(['set' => __('gallery.last_set')]);
        }

        $validated = $request->validate([
            'target_set_id' => ['nullable', 'string'],
        ]);

        $target = ($validated['target_set_id'] ?? null) === null
            ? null
            : $gallery->sets()->whereKeyNot($gallerySet->id)
                ->where('public_id', $validated['target_set_id'])->firstOrFail();

        $delete($gallerySet, $target);

        return back();
    }
}
