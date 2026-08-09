<?php

namespace App\Http\Middleware;

use App\Settings\Support\StudioSettingsResolver;
use Closure;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * T-08's capability gate: a client capability switched off refuses the
 * route it guards the same way a route that does not exist would (404,
 * never 403) — hiding the button on Contracts/Show.vue or Projects/Show.vue
 * is the affordance half of that rule, this middleware is the authorisation
 * half. Applied per-route beside `audience:portal`, never folded into it —
 * a capability is a business switch the studio flips, not an identity check.
 */
class EnsurePortalCapability
{
    public function handle(Request $request, Closure $next, string $capability): Response
    {
        $settings = app(StudioSettingsResolver::class);

        $enabled = match ($capability) {
            'sign_contracts' => $settings->signContractsEnabled(),
            'payment_status' => $settings->paymentStatusEnabled(),
            default => throw new InvalidArgumentException("Unknown portal capability \"{$capability}\"."),
        };

        abort_unless($enabled, 404);

        return $next($request);
    }
}
