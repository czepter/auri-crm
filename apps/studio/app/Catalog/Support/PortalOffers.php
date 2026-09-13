<?php

namespace App\Catalog\Support;

use App\Catalog\Data\PortalServiceData;
use App\Catalog\Models\Package;
use App\Crm\Models\Contact;
use App\Portal\Support\PortalSettings;

/**
 * Was ein Bestandskunde über seine Buchungen hinaus anfragen kann.
 *
 * Zwei Screens fragen dasselbe: die Übersicht und — für den Kunden mit genau
 * einer Buchung, den die Übersicht überspringt — der Projektbildschirm. Ohne
 * die zweite Stelle wäre die Funktion für den häufigsten Fall unsichtbar.
 */
class PortalOffers
{
    public function __construct(private readonly PortalSettings $settings) {}

    /** Leer, wenn das Studio nichts anbietet oder es diesem Kunden nicht zeigt. */
    public function for(?Contact $contact): array
    {
        if (! $this->settings->offersEnabled($contact)) {
            return [];
        }

        return PortalServiceData::collect(
            Package::query()->portalOffered()->with('items')->orderBy('name')->get()
        )->toArray();
    }
}
