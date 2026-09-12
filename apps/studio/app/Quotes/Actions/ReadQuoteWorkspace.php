<?php

namespace App\Quotes\Actions;

use App\Catalog\Data\CatalogItemData;
use App\Catalog\Data\PackageData;
use App\Catalog\Models\CatalogItem;
use App\Catalog\Models\Package;
use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use App\Models\User;
use App\Payments\Data\PaymentPlanData;
use App\Quotes\Data\QuoteData;

/**
 * What the quote editor needs, read the same on the project's Angebot tab and
 * on the lead's: a quote is written where the case is open.
 */
class ReadQuoteWorkspace
{
    public function __invoke(Project $project, ?User $user): array
    {
        return [
            'quotes' => QuoteData::collect(
                $project->quotes()->with(['items', 'predecessor', 'paymentStages'])->get()
            ),
            'paymentPlan' => $project->job?->paymentPlan
                ? PaymentPlanData::fromModel($project->job->paymentPlan->load('installments'))
                : null,
            // Beide Listen als Prop statt als Suchendpunkt: ein Studio führt
            // zweistellige Mengen, gefiltert wird in der Suchzeile selbst.
            'packages' => PackageData::collect(Package::active()->with('items')->orderBy('name')->get()),
            'catalogItems' => CatalogItemData::collect(CatalogItem::query()->orderBy('title')->get()),
            // §6: die Empfaengerkarte waehlt unter den Kontakten des
            // Projekts, und sie fuellt die Anschrift aus dem gewaehlten.
            'contacts' => $project->contacts()->get()->map(fn (Contact $contact) => [
                'id' => $contact->public_id,
                'name' => $contact->name,
                'street' => $contact->street,
                'postcode' => $contact->postcode,
                'city' => $contact->city,
            ])->all(),
            'canManageCatalog' => $user?->can('create', Package::class) ?? false,
        ];
    }
}
