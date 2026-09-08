<?php

namespace App\Help\Http\Controllers;

use App\Help\Actions\RenderHelpArticle;
use App\Help\Data\HelpEntry;
use App\Help\Support\HelpIndex;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class HelpController
{
    public function __construct(
        private readonly HelpIndex $index,
        private readonly RenderHelpArticle $render,
    ) {}

    public function index(Request $request)
    {
        return Inertia::render('Help/Index', [
            'aufgaben' => $this->liste($request, 'aufgabe'),
            'referenz' => $this->liste($request, 'referenz'),
        ]);
    }

    public function show(Request $request, string $slug)
    {
        $entry = $this->index->find($slug, $this->capabilities($request))
            ?? throw new NotFoundHttpException;

        return Inertia::render('Help/Show', [
            'artikel' => ($this->render)($entry),
            'aufgaben' => $this->liste($request, 'aufgabe'),
            'referenz' => $this->liste($request, 'referenz'),
        ]);
    }

    public function article(Request $request, string $slug)
    {
        $entry = $this->index->find($slug, $this->capabilities($request))
            ?? throw new NotFoundHttpException;

        return response()->json(($this->render)($entry));
    }

    public function search(Request $request)
    {
        return response()->json(
            $this->index->all()
                ->filter(fn (HelpEntry $e) => $e->faehigkeit === null
                    || in_array($e->faehigkeit, $this->capabilities($request), true))
                ->map(fn (HelpEntry $e) => [
                    'slug' => $e->slug,
                    'titel' => $e->titel,
                    'beschreibung' => $e->beschreibung,
                    'art' => $e->art,
                    'suchtext' => $e->suchtext,
                ])
                ->values(),
        );
    }

    /** @return array<array{slug: string, titel: string, beschreibung: string}> */
    private function liste(Request $request, string $art): array
    {
        return $this->index->all()
            ->filter(fn (HelpEntry $e) => $e->art === $art)
            ->filter(fn (HelpEntry $e) => $e->faehigkeit === null
                || in_array($e->faehigkeit, $this->capabilities($request), true))
            // Aufgaben sind ein Weg und stehen kuratiert; die Referenz ist ein
            // Nachschlagewerk und steht alphabetisch.
            ->sortBy(fn (HelpEntry $e) => $art === 'aufgabe' ? $e->reihenfolge ?? PHP_INT_MAX : $e->titel)
            ->map(fn (HelpEntry $e) => [
                'slug' => $e->slug,
                'titel' => $e->titel,
                'beschreibung' => $e->beschreibung,
            ])
            ->values()
            ->all();
    }

    /** @return array<string> */
    private function capabilities(Request $request): array
    {
        return array_map(
            fn ($capability) => $capability->value,
            $request->user('staff')->role->capabilities(),
        );
    }
}
