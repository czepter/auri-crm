<?php

namespace App\Settings\Support;

/**
 * Was ein Studio hinterlegt haben muss, bevor es das erste Angebot schreibt
 * und bevor es die erste Rechnung stellt.
 *
 * Der Einrichtungsassistent fragt nur die Marke ab (siehe
 * StudioOnboardingController): Anzeigename, Logo, Farbe, Anrede — das, was
 * der Kunde im Portal zuerst sieht. Firmierung und Anschrift werden fällig,
 * wenn mit dem ersten Angebot ein Portal-Link hinausgeht und das Impressum
 * jemanden erreicht; Steuernummer, Registerpaar und Bankverbindung, wenn der
 * erste Beleg entsteht.
 *
 * Die Rechnungsseite ist die frühere Fassung dessen, was IssueInvoice beim
 * Ausstellen ohnehin verweigert (§ 14 UStG) — hier steht sie vor dem
 * Formular statt hinter dem Absenden.
 */
class StudioReadiness
{
    public function __construct(private readonly StudioSettingsResolver $settings) {}

    /** @return list<string> */
    public function missingForQuote(): array
    {
        return $this->missing(['legal_name', 'legal_form', 'representative', 'street', 'postcode', 'city']);
    }

    /** @return list<string> */
    public function missingForInvoice(): array
    {
        $attributes = $this->settings->attributes();

        $missing = $this->missing(['bank_account_holder', 'bank_iban']);

        if (blank($attributes['tax_number']) && blank($attributes['vat_id'])) {
            $missing[] = 'tax_number';
        }

        if (LegalForm::requiresRegister($attributes['legal_form'] ?? null)) {
            $missing = [...$missing, ...$this->missing(['register_number', 'register_court'])];
        }

        return $missing;
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    private function missing(array $keys): array
    {
        $attributes = $this->settings->attributes();

        return array_values(array_filter($keys, fn (string $key) => blank($attributes[$key] ?? null)));
    }
}
