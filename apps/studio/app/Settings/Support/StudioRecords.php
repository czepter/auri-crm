<?php

namespace App\Settings\Support;

use App\Crm\Actions\DeriveProjectPosition;
use App\Crm\Data\ProjectRowData;
use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Kontakte und Projekte als Tabellenzeilen: die CSV-Downloads unter
 * Einstellungen › Export und das Archiv „Daten“ lesen dieselben.
 *
 * Semicolons and a BOM, like the invoice list (InvoiceExportController):
 * Excel opens a comma-separated UTF-8 file as one column of mojibake.
 */
class StudioRecords
{
    public const CONTACT_COLUMNS = ['Name', 'E-Mail', 'Telefon', 'Straße', 'PLZ', 'Ort'];

    public const PROJECT_COLUMNS = ['Projekt', 'Haupttermin', 'Ort', 'Stand', 'Kontakt'];

    public function __construct(private readonly DeriveProjectPosition $position) {}

    /** Erased contacts are placeholders now; a spreadsheet of them is noise. */
    public function contactQuery(): Builder
    {
        return Contact::query()->whereNull('dsgvo_erased_at')->orderBy('name');
    }

    /** Booked only, as on /projekte: before the booking a project is an inquiry. */
    public function projectQuery(): Builder
    {
        return Project::query()->whereHas('job')->orderBy('name');
    }

    /** @return Collection<int, array<int, string|null>> */
    public function contactRows(): Collection
    {
        return $this->contactQuery()->get()
            ->map(fn (Contact $contact) => [$contact->name, $contact->email, $contact->phone, $contact->street, $contact->postcode, $contact->city]);
    }

    /** @return Collection<int, array<int, string|null>> */
    public function projectRows(): Collection
    {
        return $this->projectQuery()
            ->with(['primaryShoot', 'lead', 'job', 'shoots', 'contacts'])
            ->get()
            ->map(fn (Project $project) => ProjectRowData::fromProject($project, $this->position))
            ->map(fn (ProjectRowData $row) => [$row->name, $row->date, $row->venue, $row->position, $row->contact]);
    }

    /**
     * @param  list<string>  $columns
     * @param  iterable<array<int, string|null>>  $rows
     */
    public static function csv(array $columns, iterable $rows): string
    {
        $out = fopen('php://temp', 'w+');

        fwrite($out, "\u{FEFF}");
        fputcsv($out, $columns, ';');

        foreach ($rows as $row) {
            fputcsv($out, $row, ';');
        }

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    /** No thousands separator: a grouped number is a second thing for Excel to parse. */
    public static function euro(int $minorUnits): string
    {
        return number_format($minorUnits / 100, 2, ',', '');
    }
}
