<?php

namespace App\Payments\Http\Controllers;

use App\Concerns\AuthorizesStaffSettings;
use App\Http\Controllers\Controller;
use App\Payments\Models\Invoice;
use App\Payments\Support\InvoiceFigures;
use App\Settings\Support\StudioRecords;
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

        $invoices = Invoice::query()
            ->whereYear('issued_at', $year)
            ->with(['installments', 'creditNotes', 'contact', 'project', 'items'])
            ->orderBy('number')
            ->get();

        return response()->streamDownload(function () use ($invoices, $settings) {
            $out = fopen('php://output', 'w');

            fwrite($out, "\u{FEFF}");
            fputcsv($out, self::COLUMNS, ';');

            foreach ($invoices as $invoice) {
                fputcsv($out, $this->row($invoice, $settings), ';');
            }

            fclose($out);
        }, "rechnungen-{$year}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return list<string> */
    private function row(Invoice $invoice, StudioSettingsResolver $settings): array
    {
        $figures = InvoiceFigures::forInvoice($invoice, $settings);
        $credited = $invoice->creditedAmount();

        return [
            $invoice->number,
            $invoice->issued_at->format('d.m.Y'),
            $invoice->contact?->name ?? '',
            $invoice->project?->name ?? '',
            StudioRecords::euro($figures->net),
            $figures->ratePercentage(),
            StudioRecords::euro($figures->vat),
            StudioRecords::euro($figures->gross),
            $invoice->isPaid() ? $invoice->installments->max('paid_on')->format('d.m.Y') : '',
            __("payments.invoiceStatus.{$invoice->status()}"),
            $credited === 0 ? '' : StudioRecords::euro($credited),
        ];
    }
}
