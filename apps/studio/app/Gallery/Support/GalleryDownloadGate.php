<?php

namespace App\Gallery\Support;

use App\Gallery\Models\Gallery;
use App\Media\Enums\ZipBuildSize;
use App\Payments\Models\Invoice;
use Illuminate\Support\Collection;

/**
 * Ob diese Galerie gerade Downloads herausgibt. Die Regel selbst steht an der
 * Galerie (download_rule), die Zahlung an den Rechnungen des Projekts; geprüft
 * wird bei jedem Aufruf, damit eine nachträglich stornierte oder neu gestellte
 * Rechnung auch schon vorbereitete Archive sperrt.
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
            'after_payment' => self::unpaid($gallery)->isEmpty() && self::required($gallery)->isNotEmpty(),
            default => false,
        };
    }

    /** Die Rechnung, die der Kunde als Nächstes bezahlen muss, oder null. */
    public static function invoiceToPay(Gallery $gallery): ?Invoice
    {
        return $gallery->download_rule === 'after_payment' ? self::unpaid($gallery)->first() : null;
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
