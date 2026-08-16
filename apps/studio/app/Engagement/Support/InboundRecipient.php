<?php

namespace App\Engagement\Support;

use App\Settings\Models\StudioSettings;

/**
 * Which studio account, if any, an inbound address belongs to.
 *
 * This runs **before anything is stored**, and that ordering is the whole
 * point. An address that resolves to no account is mail this system has no
 * lawful basis to keep: nobody consented, no contract covers it, and the
 * sender has no relationship with any studio here. Writing its sender and
 * body into a triage tray "so a human can look" would be processing personal
 * data on the strength of it having arrived, which is not a basis.
 *
 * It is also a tenancy bug and not only a privacy one. `inbound_messages`
 * takes its `tenant_id` from the ambient tenant, so an unresolvable delivery
 * would be filed under whichever studio the poller happened to be running as
 * — one studio's tray showing a stranger's mail addressed to nobody.
 *
 * Two shapes resolve, and nothing else does:
 *
 * - `t.<thread-public-id>.<hmac>` — the HMAC verifies under exactly one
 *   tenant's secret, and that tenant owns it.
 * - `<handle>.<hmac>` — the account catch-all, verified the same way.
 *
 * Every tenant's secret is tried because the address alone does not say whose
 * it is; verification is what identifies the account, which is also why a
 * rotated-away address resolves to nobody rather than to its old owner.
 */
class InboundRecipient
{
    public function __construct(
        public readonly string $tenantId,
        public readonly string $secret,
        /** Null for a catch-all delivery — it belongs to the account, not to one conversation. */
        public readonly ?string $threadPublicId,
    ) {}

    public static function resolve(string $address): ?self
    {
        $local = mb_strtolower(str_contains($address, '@') ? strstr($address, '@', true) : $address);

        if ($local === '') {
            return null;
        }

        // withoutGlobalScope: this runs outside any one tenant's request —
        // the poller has no tenant until this method names one.
        $accounts = StudioSettings::query()
            ->withoutGlobalScope('tenant')
            ->whereNotNull('inbound_secret')
            ->get(['tenant_id', 'inbound_secret', 'display_name']);

        foreach ($accounts as $account) {
            $secret = (string) $account->inbound_secret;

            if ($threadId = InboundAddress::threadIdFrom($local, $secret)) {
                return new self((string) $account->tenant_id, $secret, $threadId);
            }

            if (self::isCatchAll($local, (string) $account->display_name, $secret)) {
                return new self((string) $account->tenant_id, $secret, null);
            }
        }

        return null;
    }

    /**
     * `<handle>.<hmac>`, where the handle is this account's slugged name.
     * Compared in constant time, same as the thread address: the catch-all is
     * as much a credential as the reply address is.
     */
    private static function isCatchAll(string $local, string $displayName, string $secret): bool
    {
        $expected = InboundAddress::catchAll($displayName, $secret);

        if ($expected === null) {
            return false;
        }

        return hash_equals(mb_strtolower(strstr($expected, '@', true)), $local);
    }
}
