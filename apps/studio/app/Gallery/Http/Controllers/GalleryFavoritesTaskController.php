<?php

namespace App\Gallery\Http\Controllers;

use App\Gallery\Actions\CreateFavoritesTask;
use App\Gallery\Models\Gallery;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class GalleryFavoritesTaskController extends Controller
{
    public function store(Gallery $gallery, CreateFavoritesTask $create): RedirectResponse
    {
        $this->authorize('manageMedia', $gallery);
        abort_if($gallery->case === null, 422);
        $this->authorize('manageTasks', $gallery->case);

        $create($gallery, auth('staff')->user());

        return back();
    }
}
