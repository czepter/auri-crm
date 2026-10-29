<?php

namespace App\Documents\Support;

use App\Documents\Contracts\MergeDataProvider;
use App\Models\StudioSettings;
use Illuminate\Container\Attributes\Tag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;

/**
 * The one place a merge-field array is assembled from real data — T-12's
 * "resolved from one place" acceptance criterion. Every document and mail
 * reads it from here, so `{{ project.name }}` can never mean two different
 * things depending which document reads it. #565: each module supplies its
 * own blocks through MergeDataProvider; an invoice adds its own on top
 * (App\Payments\Support\InvoiceMergeData).
 *
 * Called at render time, never live from a controller — see RenderContractJob's
 * own doc comment for why: a document that read project data live would
 * silently change after a client already saw it.
 */
class DocumentMergeData
{
    /** @param  iterable<MergeDataProvider>  $providers */
    public function __construct(
        #[Tag(MergeDataProvider::class)]
        private readonly iterable $providers,
    ) {}

    /**
     * Without a case every block is still present, as empty strings, so a
     * merge field used on the wrong document renders as nothing instead of
     * leaving a raw placeholder in a PDF.
     *
     * @return array<string, mixed>
     */
    public function forCase(?Model $case): array
    {
        $data = [];

        foreach ($this->providers as $provider) {
            $data = [...$data, ...$provider->blocks($case)];
        }

        return [...$data, 'studio' => $this->studio()];
    }

    /**
     * A legal text belongs to the studio, not to a job: an Impressum or a
     * Widerrufsbelehrung names the same address whoever reads it. Everything
     * a project would fill stays out, so `{{ contact.name }}` on such a
     * template resolves to nothing rather than to the last reader.
     *
     * @return array<string, mixed>
     */
    public function forStudio(): array
    {
        return ['studio' => $this->studio()];
    }

    public static function money(int $minorUnits): string
    {
        return Number::currency($minorUnits / 100, in: 'EUR', locale: 'de');
    }

    /** @return array<string, string> */
    private function studio(): array
    {
        $studio = StudioSettings::current();

        return [
            'name' => $studio->legal_name ?: ($studio->display_name ?? ''),
            'legalForm' => $studio->legal_form ?? '',
            'street' => $studio->street ?? '',
            'postcode' => $studio->postcode ?? '',
            'city' => $studio->city ?? '',
            'country' => $studio->country ?? '',
            'taxNumber' => $studio->tax_number ?? '',
            'vatId' => $studio->vat_id ?? '',
            'registerNumber' => $studio->register_number ?? '',
            'registerCourt' => $studio->register_court ?? '',
            'representative' => $studio->representative ?? '',
            'website' => $studio->website ?? '',
            'bankHolder' => $studio->bank_account_holder ?? '',
            'bankIban' => $studio->bank_iban ?? '',
            'bankBic' => $studio->bank_bic ?? '',
            // The stored file path, not a URL — no template embeds it as
            // an <img> today; see docs/documents.md.
            'logoPath' => $studio->logo_path ?? '',
        ];
    }
}
