<?php

namespace App\Privacy\Actions;

use App\Crm\Models\Contact;
use App\Models\User;
use App\Privacy\Data\RetentionPolicyData;
use App\Privacy\Support\RetentionCandidates;
use App\Support\AuditLogger;

/** Wendet die Wahl des Studios auf jede Anfrage an, die seit `$months` Monaten ruht; gibt die Zahl der Kontakte zurück. */
class ApplyRetention
{
    public function __construct(private EraseContactDsgvo $erase) {}

    public function __invoke(int $months, ?User $actor = null): int
    {
        $policy = RetentionPolicyData::current();
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
