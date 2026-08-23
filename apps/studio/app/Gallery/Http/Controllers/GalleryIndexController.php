<?php

namespace App\Gallery\Http\Controllers;

use App\Crm\Models\Project;
use App\Gallery\Data\GalleryRowData;
use App\Gallery\Models\Gallery;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Die Liste, die es bis #255 nicht gab: eine Galerie war nur über ihr Projekt
 * oder ihren Kontakt erreichbar, und in der Nav stand sie gar nicht.
 *
 * Heißt GalleryIndexController und nicht GalleryController, weil den Namen
 * bereits App\Portal\Http\Controllers\GalleryController trägt und, für
 * dieselbe Entität, GalleryRecordController.
 */
class GalleryIndexController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Gallery::class);

        $galleries = Gallery::query()
            ->with(['project', 'currentVersion'])
            // Die eine Ausnahme von der Projektsichtbarkeit: eine Galerie ohne
            // Buchung hat nichts, woran ein Filter greifen könnte.
            ->where(fn ($query) => $query
                ->whereNull('project_id')
                ->orWhereIn('project_id', Project::query()->visibleTo(auth('staff')->user())->select('id')))
            ->latest()
            ->get();

        return Inertia::render('Galleries/Index', [
            'galleries' => GalleryRowData::collect($galleries),
        ]);
    }
}
