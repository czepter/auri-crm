<?php

namespace App\Gallery\Http\Controllers;

use App\Crm\Models\ClientCase;
use App\Gallery\Actions\CreateProjectGallery;
use App\Gallery\Models\Gallery;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjectGalleryController extends Controller
{
    public function store(Request $request, ClientCase $case, CreateProjectGallery $create): RedirectResponse
    {
        $this->authorize('create', Gallery::class);
        $this->authorize('manageShoots', $case);

        $validated = $request->validate(['shoot_id' => ['nullable', 'string']]);

        $gallery = $create($case, $validated['shoot_id'] ?? null, auth('staff')->user());

        return redirect("/galerien/{$gallery->public_id}");
    }
}
