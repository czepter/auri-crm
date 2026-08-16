<?php

namespace App\Documents\Support;

use App\Crm\Models\Project;
use App\Payments\Data\PaymentPlanData;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Support\InvoiceFigures;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Support\Number;

/**
 * The one place a merge-field array is assembled from real data — T-12's
 * "resolved from one place" acceptance criterion. App\Contracts\Jobs\
 * RenderContractJob calls this rather than building its own array, and
 * whatever eventually renders a quote or a transactional mail calls it too:
 * one vocabulary, one builder, so `{{ project.name }}` can never mean two
 * different things depending which document reads it.
 *
 * Called at render time, never live from a controller — see RenderContractJob's
 * own doc comment for why: a document that read project data live would
 * silently change after a client already saw it.
 */
class DocumentMergeDataBuilder
{
    /**
     * $installment is the one argument that is not derivable from the
     * project: an invoice is about one due amount, not about the job as a
     * whole, and only the caller rendering one knows which. Every other
     * document type passes null and the `invoice` block resolves to empty
     * strings — present in the array rather than absent, so a merge field
     * used on the wrong document type renders as nothing instead of leaving
     * a raw placeholder in a PDF.
     *
     * @return array<string, mixed>
     */
    public function fromProject(Project $project, StudioSettingsResolver $settings, ?PaymentInstallment $installment = null): array
    {
        $contact = $project->primaryContact();
        $studio = $settings->attributes();

        return [
            'project' => [
                'name' => $project->name,
                'type' => $project->type->label(),
            ],
            'contact' => [
                'name' => $contact?->name ?? '',
                'email' => $contact?->email ?? '',
                'street' => $contact?->street ?? '',
                'postcode' => $contact?->postcode ?? '',
                'city' => $contact?->city ?? '',
            ],
            'invoice' => $this->invoice($installment, $project, $settings),
            'job' => [
                'bookedAt' => $project->job?->booked_at?->format('d.m.Y') ?? '',
            ],
            'studio' => [
                'name' => $studio['legal_name'] ?: ($studio['display_name'] ?? ''),
                'legalForm' => $studio['legal_form'] ?? '',
                'street' => $studio['street'] ?? '',
                'postcode' => $studio['postcode'] ?? '',
                'city' => $studio['city'] ?? '',
                'country' => $studio['country'] ?? '',
                'taxNumber' => $studio['tax_number'] ?? '',
                'vatId' => $studio['vat_id'] ?? '',
                'registerNumber' => $studio['register_number'] ?? '',
                'registerCourt' => $studio['register_court'] ?? '',
                'representative' => $studio['representative'] ?? '',
                'website' => $studio['website'] ?? '',
                'bankHolder' => $studio['bank_account_holder'] ?? '',
                'bankIban' => $studio['bank_iban'] ?? '',
                'bankBic' => $studio['bank_bic'] ?? '',
                // The stored file path, not a URL — no template embeds it as
                // an <img> today; see docs/documents.md.
                'logoPath' => $settings->logoPath() ?? '',
            ],
            'settings' => [
                'widerrufText' => $settings->widerrufIncluded() ? ($settings->widerrufText() ?? '') : '',
            ],
        ];
    }

    /**
     * The §14 UStG particulars, or empty strings for every document that is
     * not an invoice.
     *
     * The Leistungszeitraum is the primary shoot's date rather than the
     * invoice's own — the supply happened on the shoot day, and an invoice
     * issued three weeks later that claims otherwise is wrong in the one
     * field a Betriebsprüfung reads first. A project with no primary shoot
     * yet leaves it empty rather than guessing.
     *
     * @return array<string, string>
     */
    private function invoice(?PaymentInstallment $installment, Project $project, StudioSettingsResolver $settings): array
    {
        if ($installment === null) {
            return array_fill_keys(
                ['number', 'date', 'servicePeriod', 'description', 'net', 'vatRate', 'vat', 'gross', 'vatNote', 'dueOn', 'paymentTerm'],
                '',
            );
        }

        $figures = InvoiceFigures::forInstallment($installment, $settings);
        $dueOn = $installment->due_on?->format('d.m.Y') ?? '';

        return [
            'number' => $installment->invoice_number ?? '',
            'date' => $installment->invoiced_at?->format('d.m.Y') ?? '',
            'servicePeriod' => $project->shoots()->where('is_primary', true)->first()?->date?->format('d.m.Y') ?? '',
            'description' => $project->name.' — '.PaymentPlanData::labelForInstallment($installment),
            'net' => $this->money($figures->net),
            'vatRate' => $figures->ratePercentage(),
            'vat' => $this->money($figures->vat),
            'gross' => $this->money($figures->gross),
            'vatNote' => $figures->vatNote ?? '',
            'dueOn' => $dueOn,
            'paymentTerm' => $dueOn === '' ? '' : "Zahlbar bis {$dueOn} ohne Abzug.",
        ];
    }

    private function money(int $minorUnits): string
    {
        return Number::currency($minorUnits / 100, in: 'EUR', locale: 'de');
    }
}
