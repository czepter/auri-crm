<?php

namespace App\Media\Http\Controllers;

use App\Gallery\Models\Gallery;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * G-02 erkennt dieselben Bytes ueber assets_gallery_checksum_unique — aber
 * erst, nachdem sie durchs Netz sind. Diese Abfrage kostet einen
 * Rundlauf und spart 853 MB.
 *
 * Name und Groesse, nicht der Inhalt: eine Pruefsumme im Browser liest jede
 * Datei einmal komplett von der Platte, bevor irgendetwas passiert.
 */
class UploadPrecheckController extends Controller
{
    public function __invoke(Request $request, Gallery $gallery): JsonResponse
    {
        $this->authorize('manageMedia', $gallery);

        $validated = $request->validate([
            'files' => ['required', 'array'],
            'files.*.filename' => ['required', 'string', 'max:255'],
            'files.*.size' => ['required', 'integer', 'min:1'],
        ]);

        $wanted = collect($validated['files'])->keyBy(fn (array $file) => "{$file['filename']}:{$file['size']}");

        // Not inDraft(): that scope also requires status = Ready, and a
        // Failed row still carries the checksum, filename and byte_size that
        // made IngestCompletedUpload converge on it — re-picking the same
        // file must still be caught, or the bytes travel again for nothing.
        $duplicates = $gallery->assetRows()->whereNull('removed_from_draft_at')
            ->whereIn('filename', $wanted->pluck('filename'))
            ->get()
            ->filter(fn ($asset) => $wanted->has("{$asset->filename}:{$asset->byte_size}"))
            ->map(fn ($asset) => [
                'filename' => $asset->filename,
                'size' => $asset->byte_size,
                'assetId' => $asset->public_id,
            ])
            ->values();

        return response()->json(['duplicates' => $duplicates]);
    }
}
