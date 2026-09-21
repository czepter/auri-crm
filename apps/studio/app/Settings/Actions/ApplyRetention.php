<?php

namespace App\Settings\Actions;

use App\Crm\Actions\EraseContactDsgvo;
use App\Crm\Models\Contact;
use App\Models\User;
use App\Settings\Support\RetentionCandidates;
use App\Settings\Support\StudioSettingsResolver;
use App\Support\AuditLogger;

/** Wendet die Wahl des Studios auf jede Anfrage an, die seit `$months` Monaten ruht; gibt die Zahl der Kontakte zurück. */
class ApplyRetention
{
    public function __construct(private StudioSettingsResolver $settings, private EraseContactDsgvo $erase) {}

    public function __invoke(int $months, ?User $actor = null): int
    {
        $policy = $this->settings->retentionPolicy();
        $count = 0;

        RetentionCandidates::query(now()->subMonths($months))
            ->whereNotIn('id', RetentionCandidates::invoicedContactIds())
            ->each(function (Contact $contact) use ($policy, $actor, &$count) {
                ($this->erase)($contact, $actor, $policy);
                $contact->forceFill(['retention_applied_at' => now()])->save();
                $count++;
            });

        if ($count > 0) {
            AuditLogger::record('retention.erased', actor: $actor, context: ['count' => $count, 'months' => $months]);
        }

        return $count;
    }
}
