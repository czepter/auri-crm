<?php

namespace App\Http\Middleware;

use App\Portal\Support\PortalSettings;
use Closure;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * T-08's capability gate: a client capability switched off refuses the
 * route it guards the same way a route that does not exist would (404,
 * never 403) — hiding the button on Quotes/Show.vue or Projects/Show.vue
 * is the affordance half of that rule, this middleware is the authorisation
 * half. Applied per-route beside `audience:portal`, never folded into it —
 * a capability is a business switch the studio flips, not an identity check.
 */
class EnsurePortalCapability
{
    public function handle(Request $request, Closure $next, string $capability): Response
    {
        // P-09 (#228): the studio's default, layered with this contact's own
        // override. A venue that may not message and a couple that may are
        // the same switch at two levels, not two switches.
        $settings = app(PortalSettings::class);
        $contact = auth('portal')->user();

        $enabled = match ($capability) {
            'accept_quotes' => $settings->acceptQuotesEnabled($contact),
            'sign_contracts' => $settings->signContractsEnabled($contact),
            'payment_status' => $settings->paymentStatusEnabled($contact),
            'messaging' => $settings->messagingEnabled($contact),
            'offers' => $settings->offersEnabled($contact),
            default => throw new InvalidArgumentException("Unknown portal capability \"{$capability}\"."),
        };

        abort_unless($enabled, 404);

        return $next($request);
    }
}
