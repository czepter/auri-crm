<?php

namespace App\Settings\Http\Controllers;

use App\Concerns\AuthorizesStaffSettings;
use App\Crm\Actions\DeriveProjectPosition;
use App\Crm\Data\ProjectRowData;
use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use App\Http\Controllers\Controller;
use App\Payments\Models\PaymentInstallment;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The studio's records as spreadsheets, beside the invoice list the
 * Steuerberater asks for (InvoiceExportController). Same file shape as that
 * one — semicolons and a BOM — for the same reason: Excel.
 */
class DataExportController extends Controller
{
    use AuthorizesStaffSettings;

    public function index(): Response
    {
        $this->authorizeSettings();

        return Inertia::render('Settings/Export', [
            'contacts' => $this->contactQuery()->count(),
            'projects' => $this->projectQuery()->count(),
            'invoiceYears' => PaymentInstallment::query()
                ->whereNotNull('invoiced_at')
                ->pluck('invoiced_at')
                ->map(fn ($at) => (int) $at->format('Y'))
                ->unique()
                ->sortDesc()
                ->values(),
        ]);
    }

    public function contacts(): StreamedResponse
    {
        $this->authorizeSettings();

        return $this->csv('kontakte.csv', ['Name', 'E-Mail', 'Telefon', 'Straße', 'PLZ', 'Ort'], $this->contactQuery()->get()
            ->map(fn (Contact $contact) => [$contact->name, $contact->email, $contact->phone, $contact->street, $contact->postcode, $contact->city]));
    }

    public function projects(DeriveProjectPosition $position): StreamedResponse
    {
        $this->authorizeSettings();

        return $this->csv('projekte.csv', ['Projekt', 'Haupttermin', 'Ort', 'Stand', 'Kontakt'], $this->projectQuery()
            ->with(['primaryShoot', 'lead', 'job', 'shoots', 'contacts'])
            ->get()
            ->map(fn (Project $project) => ProjectRowData::fromProject($project, $position))
            ->map(fn (ProjectRowData $row) => [$row->name, $row->date, $row->venue, $row->position, $row->contact]));
    }

    /** Erased contacts are placeholders now; a spreadsheet of them is noise. */
    private function contactQuery(): Builder
    {
        return Contact::query()->whereNull('dsgvo_erased_at')->orderBy('name');
    }

    /** Booked only, as on /projekte: before the booking a project is an inquiry. */
    private function projectQuery(): Builder
    {
        return Project::query()->whereHas('job')->orderBy('name');
    }

    /** @param iterable<array<int, string|null>> $rows */
    private function csv(string $name, array $columns, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($columns, $rows) {
            $out = fopen('php://output', 'w');

            fwrite($out, "\u{FEFF}");
            fputcsv($out, $columns, ';');

            foreach ($rows as $row) {
                fputcsv($out, $row, ';');
            }

            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
