<?php

namespace App\Settings\Http\Controllers;

use App\Concerns\AuthorizesStaffSettings;
use App\Enums\StaffRole;
use App\Gallery\Models\Gallery;
use App\Http\Controllers\Controller;
use App\Media\Models\Asset;
use App\Settings\Enums\StudioExportKind;
use App\Settings\Models\StudioExport;
use App\Settings\Support\StudioRecords;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The studio's records as spreadsheets, beside the invoice list the
 * Steuerberater asks for (InvoiceExportController).
 */
class DataExportController extends Controller
{
    use AuthorizesStaffSettings;

    public function index(StudioRecords $records): Response
    {
        $this->authorizeSettings();

        return Inertia::render('Settings/Export', [
            'contacts' => $records->contactQuery()->count(),
            'projects' => $records->projectQuery()->count(),
            'archives' => auth('staff')->user()->role === StaffRole::Owner ? $this->archives() : null,
        ]);
    }

    /** @return array{daten: array<string, mixed>|null, galleries: list<array<string, mixed>>} */
    private function archives(): array
    {
        $exports = StudioExport::query()->orderByDesc('id')->get();
        $latest = fn (StudioExportKind $kind, ?int $galleryId) => $exports
            ->first(fn (StudioExport $export) => $export->kind === $kind && $export->gallery_id === $galleryId)
            ?->asRow();

        $sizes = Asset::query()->inDraft()->groupBy('gallery_id')->selectRaw('gallery_id, sum(byte_size) as size')->pluck('size', 'gallery_id');

        return [
            'daten' => $latest(StudioExportKind::Daten, null),
            'galleries' => Gallery::query()->whereIn('id', $sizes->keys())->orderBy('title')->get()
                ->map(fn (Gallery $gallery) => [
                    'id' => $gallery->public_id,
                    'title' => $gallery->title,
                    'size' => (int) $sizes[$gallery->id],
                    'archive' => $latest(StudioExportKind::Galerie, $gallery->id),
                ])->all(),
        ];
    }

    public function contacts(StudioRecords $records): StreamedResponse
    {
        $this->authorizeSettings();

        return $this->csv('kontakte.csv', StudioRecords::CONTACT_COLUMNS, $records->contactRows());
    }

    public function projects(StudioRecords $records): StreamedResponse
    {
        $this->authorizeSettings();

        return $this->csv('projekte.csv', StudioRecords::PROJECT_COLUMNS, $records->projectRows());
    }

    /** @param iterable<array<int, string|null>> $rows */
    private function csv(string $name, array $columns, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(
            fn () => print StudioRecords::csv($columns, $rows),
            $name,
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }
}
