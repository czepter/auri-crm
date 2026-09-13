<?php

namespace App\Catalog\Support;

use App\Catalog\Data\PortalServiceData;
use App\Catalog\Models\Package;
use App\Catalog\Models\ServiceSuggestion;
use App\Crm\Models\Contact;
use App\Portal\Support\PortalSettings;

/**
 * Was ein Bestandskunde über seine Buchungen hinaus anfragen kann.
 *
 * Zwei Dinge, und sie erreichen zwei verschiedene Leute: der Vorschlag ist an
 * diesen Kunden geschrieben, das Regal sieht jeder gleich. Was einzeln
 * vorgeschlagen wurde, fällt aus dem Regal — zweimal dieselbe Leistung auf
 * einem Screen ist keine zweite Gelegenheit.
 *
 * Drei Screens fragen dasselbe: die Übersicht, der Projektbildschirm (für den
 * Kunden mit genau einer Buchung, den die Übersicht überspringt) und die
 * Leistungsseite.
 */
class PortalOffers
{
    public function __construct(private readonly PortalSettings $settings) {}

    /**
     * @return array{suggestion: ?array<string, mixed>, shelf: list<array<string, mixed>>}
     */
    public function for(?Contact $contact): array
    {
        if (! $this->settings->offersEnabled($contact)) {
            return ['suggestion' => null, 'shelf' => []];
        }

        $suggestion = $this->suggestion($contact);

        $shelf = Package::query()->portalOffered()->with('items')->orderBy('name')->get()
            ->reject(fn (Package $package) => $package->public_id === ($suggestion['service']['id'] ?? null));

        return [
            'suggestion' => $suggestion,
            'shelf' => PortalServiceData::collect($shelf->values())->toArray(),
        ];
    }

    /** @return ?array<string, mixed> */
    private function suggestion(?Contact $contact): ?array
    {
        if ($contact === null) {
            return null;
        }

        $row = ServiceSuggestion::query()
            ->where('contact_id', $contact->id)
            ->live()
            ->with('package.items', 'suggestedBy')
            ->latest()
            ->first();

        return $row === null ? null : [
            'service' => PortalServiceData::fromModel($row->package)->toArray(),
            'note' => $row->note,
            // Der Vorname reicht und ist der, den der Kunde kennt.
            'author' => $row->suggestedBy === null ? null : explode(' ', $row->suggestedBy->name)[0],
        ];
    }

    /** Der Satz zu genau dieser Leistung, wenn sie diesem Kunden vorgeschlagen wurde. */
    public function noteFor(?Contact $contact, Package $package): ?array
    {
        $suggestion = $this->suggestion($contact);

        return ($suggestion['service']['id'] ?? null) === $package->public_id ? $suggestion : null;
    }
}
