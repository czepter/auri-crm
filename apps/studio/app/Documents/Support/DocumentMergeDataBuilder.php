<?php

namespace App\Documents\Support;

use App\Crm\Models\Project;
use App\Payments\Data\PaymentPlanData;
use App\Payments\Models\CreditNote;
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
     * $installment and $creditNote are the two arguments not derivable from
     * the project: an invoice is about one due amount and a Gutschrift about
     * one refund of it, not about the job as a whole, and only the caller
     * rendering one knows which. Every other document type passes null and
     * those blocks resolve to empty strings — present in the array rather
     * than absent, so a merge field used on the wrong document type renders
     * as nothing instead of leaving a raw placeholder in a PDF.
     *
     * @return array<string, mixed>
     */
    public function fromProject(
        Project $project,
        StudioSettingsResolver $settings,
        ?PaymentInstallment $installment = null,
        ?CreditNote $creditNote = null,
    ): array {
        $contact = $project->primaryContact();
        $studio = $settings->attributes();

        return [
            'project' => [
                'name' => $project->name,
                'type' => $project->type->name,
            ],
            'contact' => [
                'name' => $contact?->name ?? '',
                'email' => $contact?->email ?? '',
                'street' => $contact?->street ?? '',
                'postcode' => $contact?->postcode ?? '',
                'city' => $contact?->city ?? '',
            ],
            'invoice' => $this->invoice($installment, $project, $settings),
            'creditNote' => $this->creditNote($creditNote, $project, $settings),
            'job' => [
                'bookedAt' => $project->job?->booked_at?->format('d.m.Y') ?? '',
            ],
            // The occasion this document is about. Same primary shoot the
            // invoice's Leistungszeitraum reads, named plainly here because a
            // quote and a contract both talk about the day rather than about
            // a service period.
            'shoot' => [
                'date' => $project->primaryShoot?->date?->format('d.m.Y') ?? '',
            ],
            // What a quote says about itself. Empty on a project with no sent
            // quote — a contract template referencing them is asking about a
            // document that does not exist, which is exactly the case an
            // empty string is right for.
            'quote' => $this->quote($project, $settings),
            // C-17 (#253): the gallery this project delivered, with the two
            // secrets in clear. Empty where none is published — a template
            // that names them on a project without a gallery is asking about
            // something that does not exist.
            'gallery' => $this->gallery($project),
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
     * The newest gallery of this project, or empty strings.
     *
     * The link is apps/gallery's own address (Gallery::publicUrl()) — the
     * guest viewer, not this app's own host, which no guest can open.
     *
     * @return array<string, string>
     */
    private function gallery(Project $project): array
    {
        $gallery = $project->galleries()->latest('id')->first();

        return [
            'title' => $gallery?->title ?? '',
            'link' => $gallery?->publicUrl() ?? '',
            'password' => $gallery?->view_password ?? '',
            'downloadPin' => $gallery?->download_pin ?? '',
            'expiresOn' => $gallery?->expires_at?->format('d.m.Y') ?? '',
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
     * An Anzahlung whose transfer arrived before the paperwork prints the
     * day it arrived instead: § 14 Abs. 4 Nr. 6 UStG asks for the Zeitpunkt
     * der Vereinnahmung in an Abs. 5 case, and the shoot is still ahead.
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
        // paid_on is only ever written alongside the paid status, by
        // MarkInstallmentPaid — its presence is the settlement.
        $receivedOn = $installment->paid_on !== null
            && $installment->invoiced_at !== null
            && $installment->paid_on->lt($installment->invoiced_at)
                ? $installment->paid_on
                : null;

        return [
            'number' => $installment->invoice_number ?? '',
            'date' => $installment->invoiced_at?->format('d.m.Y') ?? '',
            'servicePeriod' => $receivedOn?->format('d.m.Y')
                ?? $project->shoots()->where('is_primary', true)->first()?->date?->format('d.m.Y') ?? '',
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

    /**
     * #409: the Gutschrift's own particulars, or empty strings on every
     * document that is not one.
     *
     * The split runs off the credit note's amount rather than the
     * installment's, so a partial Gutschrift states the VAT actually being
     * given back; the rate itself still comes from the installment, because
     * a refund carries the tax rate of the supply it reverses, not today's.
     *
     * @return array<string, string>
     */
    private function creditNote(?CreditNote $creditNote, Project $project, StudioSettingsResolver $settings): array
    {
        $keys = ['number', 'date', 'servicePeriod', 'description', 'reason', 'net', 'vatRate', 'vat', 'gross', 'vatNote', 'invoiceNumber', 'invoiceDate'];

        if ($creditNote === null) {
            return array_fill_keys($keys, '');
        }

        $installment = $creditNote->installment;
        $rate = InvoiceFigures::forInstallment($installment, $settings);
        $figures = InvoiceFigures::fromGross($creditNote->amount, $rate->rate, $rate->vatNote);

        return [
            'number' => $creditNote->number,
            'date' => $creditNote->issued_at->format('d.m.Y'),
            'servicePeriod' => $project->shoots()->where('is_primary', true)->first()?->date?->format('d.m.Y') ?? '',
            'description' => $project->name.' — '.PaymentPlanData::labelForInstallment($installment),
            'reason' => $creditNote->reason,
            'net' => $this->money($figures->net),
            'vatRate' => $figures->ratePercentage(),
            'vat' => $this->money($figures->vat),
            'gross' => $this->money($figures->gross),
            'vatNote' => $figures->vatNote ?? '',
            'invoiceNumber' => $installment->invoice_number ?? '',
            'invoiceDate' => $installment->invoiced_at?->format('d.m.Y') ?? '',
        ];
    }

    /**
     * Validity and the VAT sentence, frozen on the quote at send (Q-04/Q-06)
     * rather than recomputed here: a client reading a quote must see the rule
     * that applied when it went out, not the one the studio switched to
     * afterwards.
     *
     * @return array<string, string>
     */
    private function quote(Project $project, StudioSettingsResolver $settings): array
    {
        $quote = $project->latestQuote;

        if ($quote === null) {
            return ['validUntil' => '', 'vatNote' => ''];
        }

        return [
            'validUntil' => $quote->valid_until?->format('d.m.Y') ?? '',
            // A standard-rate quote froze no note — there is nothing unusual
            // to say — so the sentence is composed from the frozen rate. Only
            // the small-business case carries stored wording.
            'vatNote' => $quote->vat_note
                ?? ($quote->vat_rate === null || $quote->vat_rate === 0
                    ? ''
                    : 'Alle Preise verstehen sich zzgl. '.rtrim(rtrim(number_format($quote->vat_rate / 100, 2, ',', '.'), '0'), ',').' % Umsatzsteuer.'),
        ];
    }

    private function money(int $minorUnits): string
    {
        return Number::currency($minorUnits / 100, in: 'EUR', locale: 'de');
    }
}
