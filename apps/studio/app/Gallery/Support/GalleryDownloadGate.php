<?php

namespace App\Gallery\Support;

use App\Gallery\Models\Gallery;
use App\Media\Enums\ZipBuildSize;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Models\Invoice;
use App\Payments\Models\PaymentInstallment;
use Illuminate\Support\Collection;

/**
 * Ob diese Galerie gerade Downloads herausgibt. Die Regel selbst steht an der
 * Galerie (download_rule), die Zahlung an den Raten des Zahlungsplans, bei
 * gewählter Rechnung an dieser; geprüft wird bei jedem Aufruf, damit eine
 * nachträglich stornierte oder neu gestellte Rechnung auch schon vorbereitete
 * Archive sperrt.
 */
final class GalleryDownloadGate
{
    public static function offered(Gallery $gallery): bool
    {
        return $gallery->download_rule !== 'off';
    }

    public static function allows(Gallery $gallery): bool
    {
        return match ($gallery->download_rule) {
            'open' => true,
            'after_payment' => self::paid($gallery),
            default => false,
        };
    }

    /** Die Rechnung, die der Kunde als Nächstes bezahlen muss, oder null. */
    public static function invoiceToPay(Gallery $gallery): ?Invoice
    {
        if ($gallery->download_rule !== 'after_payment') {
            return null;
        }

        $rows = self::planRows($gallery);

        return $rows === null ? self::unpaid($gallery)->first() : self::nextUnpaid($rows)?->invoice;
    }

    /** Die offene Rate des Zahlungsplans, für die es noch keine Rechnung gibt: Betrag und Fälligkeit statt Knopf. */
    public static function unbilled(Gallery $gallery): ?PaymentInstallment
    {
        $rows = $gallery->download_rule === 'after_payment' ? self::planRows($gallery) : null;
        $next = $rows === null ? null : self::nextUnpaid($rows);

        return $next?->invoice_id === null ? $next : null;
    }

    /** Ob „Alle Rechnungen“ für dieses Projekt der Zahlungsplan ist. */
    public static function followsPlan(Gallery $gallery): bool
    {
        return self::planRows($gallery, ignoreChoice: true) !== null;
    }

    /** Wohin der Kunde für die offene Rechnung geht; ein Gast hat keinen Zugang zum Portal. */
    public static function payUrl(Gallery $gallery): ?string
    {
        $invoice = self::invoiceToPay($gallery);

        return $invoice === null
            ? null
            : rtrim((string) config('portal.url'), '/')."/projekte/{$gallery->case->public_id}/zahlungen/{$invoice->public_id}";
    }

    /** @return list<ZipBuildSize> */
    public static function sizes(Gallery $gallery): array
    {
        return collect($gallery->download_sizes ?? [])
            ->map(fn (string $size) => ZipBuildSize::tryFrom($size))
            ->filter()
            ->values()
            ->all();
    }

    public static function allowsSize(Gallery $gallery, ZipBuildSize $size): bool
    {
        return in_array($size, self::sizes($gallery), true);
    }

    private static function paid(Gallery $gallery): bool
    {
        $rows = self::planRows($gallery);

        return $rows === null
            ? self::unpaid($gallery)->isEmpty() && self::required($gallery)->isNotEmpty()
            : self::nextUnpaid($rows) === null;
    }

    /**
     * Die Raten des Projekts ohne stornierte, wenn keine bestimmte Rechnung
     * gewählt ist und es einen Zahlungsplan gibt; sonst null.
     *
     * @return Collection<int, PaymentInstallment>|null
     */
    private static function planRows(Gallery $gallery, bool $ignoreChoice = false): ?Collection
    {
        if ($gallery->case_id === null || (! $ignoreChoice && $gallery->download_invoice_id !== null)) {
            return null;
        }

        $rows = PaymentInstallment::query()
            ->whereNull('cancelled_at')
            ->whereDoesntHave('invoice', fn ($invoice) => $invoice->whereNotNull('cancelled_at'))
            ->where(fn ($query) => $query
                ->whereHas('plan', fn ($plan) => $plan->where('case_id', $gallery->case_id))
                ->orWhereHas('invoice', fn ($invoice) => $invoice->where('case_id', $gallery->case_id)))
            ->with('invoice')
            ->orderBy('due_on')->orderBy('sort_order')
            ->get();

        $hasPlan = $rows->contains(fn (PaymentInstallment $row) => $row->payment_plan_id !== null);

        return $hasPlan ? $rows : null;
    }

    /** @param Collection<int, PaymentInstallment> $rows */
    private static function nextUnpaid(Collection $rows): ?PaymentInstallment
    {
        return $rows->first(fn (PaymentInstallment $row) => $row->status !== PaymentInstallmentStatus::Paid);
    }

    /** @return Collection<int, Invoice> */
    private static function required(Gallery $gallery): Collection
    {
        if ($gallery->case_id === null) {
            return collect();
        }

        $invoices = Invoice::query()
            ->where('case_id', $gallery->case_id)
            ->whereNull('cancelled_at')
            ->with('installments');

        if ($gallery->download_invoice_id !== null) {
            $invoices->whereKey($gallery->download_invoice_id);
        }

        return $invoices->orderBy('issued_at')->get();
    }

    /** @return Collection<int, Invoice> */
    private static function unpaid(Gallery $gallery): Collection
    {
        return self::required($gallery)->reject(fn (Invoice $invoice) => $invoice->isPaid())->values();
    }
}
