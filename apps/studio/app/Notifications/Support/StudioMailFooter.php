<?php

namespace App\Notifications\Support;

use App\Models\StudioSettings;
use Illuminate\Support\Collection;

/**
 * The studio's signature and legal particulars, as one escaped HTML fragment
 * for the mail layout's footer zone.
 *
 * A class rather than a method on App\Concerns\AppliesStudioMailIdentity,
 * because the caller is App\Providers\AppServiceProvider's `mail::layout`
 * composer and not a notification: Blade does not pass a view's data into a
 * nested component, so a MailMessage cannot hand this to the layout the way
 * it hands the body its lines. The composer is the only place that reaches
 * the layout's own scope — the same reason `brand` is resolved there.
 *
 * The particulars are the ones App\Contracts\Jobs\RenderContractJob's
 * `studio.*` merge fields read, from StudioSettings::profile(),
 * so the mail footer and the rendered contract cannot drift apart.
 */
final class StudioMailFooter
{
    /** The settings screen's preview passes the signature as typed, before it is saved. */
    public function __construct(private readonly ?string $signature = null) {}

    public function __invoke(): ?string
    {
        $studio = StudioSettings::current()->profile();

        $legal = Collection::make([
            $studio['legal_name'] ?: $studio['display_name'],
            Collection::make([$studio['street'], trim(($studio['postcode'] ?? '').' '.($studio['city'] ?? ''))])
                ->filter()->implode(', '),
            Collection::make([
                $studio['tax_number'] ? "St.-Nr. {$studio['tax_number']}" : null,
                $studio['vat_id'] ? "USt-IdNr. {$studio['vat_id']}" : null,
            ])->filter()->implode(' · '),
        ])->filter()->implode(' · ');

        $lines = Collection::make([$this->signature ?? StudioSettings::current()->mail_signature, $legal ?: null])
            ->filter()
            ->map(fn (string $line) => e($line));

        return $lines->isEmpty() ? null : $lines->implode('<br>');
    }
}
