<?php

namespace App\Portal\Actions;

use App\Crm\Models\Contact;
use App\Portal\Models\ClientAccess;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Spends a magic link and returns the contact it belonged to, or null.
 *
 * Single use is enforced in the database rather than by reading the row and
 * then writing it: two requests arriving with the same token at the same
 * moment would both pass a read-then-write check, and one link would sign in
 * twice. The conditional update lets exactly one of them claim it.
 */
class ConsumeClientAccess
{
    public function __invoke(string $token, ?string $ip = null): ?Contact
    {
        return DB::transaction(function () use ($token, $ip) {
            $access = ClientAccess::forToken($token);

            if ($access === null) {
                return null;
            }

            // The claim: only the request that flips consumed_at from null
            // wins, so a raced link is spent once however many arrive.
            $claimed = ClientAccess::query()
                ->whereKey($access->id)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);

            if ($claimed === 0) {
                return null;
            }

            AuditLogger::record(
                event: 'portal.link_consumed',
                context: ['contact' => $access->contact->public_id, 'ip' => $ip],
                subject: $access->public_id,
            );

            return $access->contact;
        });
    }
}
