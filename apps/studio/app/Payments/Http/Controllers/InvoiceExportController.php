<?php

namespace App\Payments\Http\Controllers;

use App\Concerns\AuthorizesStaffSettings;
use App\Http\Controllers\Controller;
use App\Payments\Models\CreditNote;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Support\InvoiceFigures;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * #416: the list the Steuerberater asks for. One row per issued invoice of
 * one year, in number order — Storno rows included, because an invoice that
 * was retired is part of the sequence and a gap in it is the question the
 * export exists to answer.
 *
 * Semicolons and a BOM: Excel opens a comma-separated UTF-8 file as one
 * column of mojibake, and nobody who needs this list will convert it first.
 */
class InvoiceExportController extends Controller
{
    use AuthorizesStaffSettings;

    private const COLUMNS = [
        'Nummer', 'Datum', 'Kunde', 'Projekt', 'Netto', 'Steuersatz', 'Steuer',
        'Brutto', 'bezahlt am', 'Status', 'Gutschriften',
    ];

    public function __invoke(Request $request, StudioSettingsResolver $settings): StreamedResponse
    {
        $this->authorizeSettings();

        $year = (int) $request->validate([
            'jahr' => ['required', 'integer', 'min:2000', 'max:2100'],
        ])['jahr'];

        $installments = PaymentInstallment::query()
            ->whereNotNull('invoice_number')
            ->whereYear('invoiced_at', $year)
            ->with(['creditNotes', 'plan.job.project'])
            ->orderBy('invoice_number')
            ->get();

        return response()->streamDownload(function () use ($installments, $settings) {
            $out = fopen('php://output', 'w');

            fwrite($out, "\u{FEFF}");
            fputcsv($out, self::COLUMNS, ';');

            foreach ($installments as $installment) {
                fputcsv($out, $this->row($installment, $settings), ';');
            }

            fclose($out);
        }, "rechnungen-{$year}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return list<string> */
    private function row(PaymentInstallment $installment, StudioSettingsResolver $settings): array
    {
        $figures = InvoiceFigures::forInstallment($installment, $settings);
        $project = $installment->plan?->job?->project;
        $credited = $installment->creditNotes->sum(fn (CreditNote $note) => (int) $note->amount);

        return [
            (string) $installment->invoice_number,
            $installment->invoiced_at?->format('d.m.Y') ?? '',
            $project?->primaryContact()?->name ?? '',
            $project?->name ?? '',
            $this->euro($figures->net),
            $figures->ratePercentage(),
            $this->euro($figures->vat),
            $this->euro($figures->gross),
            $installment->paid_on?->format('d.m.Y') ?? '',
            $installment->status->label(),
            $credited === 0 ? '' : $this->euro($credited),
        ];
    }

    /** No thousands separator: a grouped number is a second thing for Excel to parse. */
    private function euro(int $minorUnits): string
    {
        return number_format($minorUnits / 100, 2, ',', '');
    }
}
