<?php

namespace App\Settings\Http\Controllers;

use App\Enums\StaffRole;
use App\Gallery\Models\Gallery;
use App\Http\Controllers\Controller;
use App\Settings\Actions\RequestStudioExport;
use App\Settings\Enums\StudioExportKind;
use App\Settings\Models\StudioExport;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * #489: Archive erzeugen und laden. Nur der Inhaber; jeder andere bekommt
 * dieselbe 404 wie bei AuthorizesStaffSettings.
 */
class StudioExportController extends Controller
{
    public function store(Request $request, RequestStudioExport $export): RedirectResponse
    {
        self::authorizeOwner();

        $publicId = $request->validate(['gallery' => ['nullable', 'string']])['gallery'] ?? null;
        $gallery = $publicId === null ? null : Gallery::query()->where('public_id', $publicId)->firstOrFail();

        $export($gallery === null ? StudioExportKind::Daten : StudioExportKind::Galerie, $gallery, $request->user('staff'));

        return back();
    }

    /** Weiter zu einem kurzlebigen S3-Link, damit ein Archiv von Gigabytes nicht durch PHP läuft. */
    public function download(StudioExport $studioExport): RedirectResponse
    {
        self::authorizeOwner();
        abort_unless($studioExport->isDownloadable(), 404);

        AuditLogger::record('studio_export.downloaded', auth('staff')->user(), subject: $studioExport->public_id);

        $name = $studioExport->kind === StudioExportKind::Daten
            ? 'daten'
            : 'galerie-'.Str::slug($studioExport->gallery->title);

        return redirect()->away(Storage::disk('s3')->temporaryUrl($studioExport->path, now()->addMinutes(5), [
            'ResponseContentDisposition' => "attachment; filename=\"{$name}-{$studioExport->created_at->format('Y-m-d')}.zip\"",
        ]));
    }

    private static function authorizeOwner(): void
    {
        abort_unless(auth('staff')->user()?->role === StaffRole::Admin, 404);
    }
}
