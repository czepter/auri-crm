<?php

namespace App\Portal\Actions;

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
    /**
     * Returns the spent row rather than the Contact alone: P-09's preview
     * rides on the same link (ClientAccess::isPreview()), and the caller has
     * to be able to tell the two apart before it opens a session.
     */
    public function __invoke(string $token, ?string $ip = null): ?ClientAccess
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
                event: $access->isPreview() ? 'portal.preview_entered' : 'portal.link_consumed',
                context: [
                    'contact' => $access->contact->public_id,
                    'ip' => $ip,
                    ...$access->isPreview() ? ['staff' => $access->previewBy?->public_id] : [],
                ],
                subject: $access->public_id,
            );

            return $access;
        });
    }
}
