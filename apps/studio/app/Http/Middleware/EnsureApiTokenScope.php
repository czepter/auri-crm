<?php

namespace App\Http\Middleware;

use App\Crm\Models\ClientCase;
use App\Crm\Models\CrewAssignment;
use App\Crm\Models\FormInstanceField;
use App\Crm\Models\ShotListItem;
use App\Crm\Models\TimelineEntry;
use App\Engagement\Models\InboundMessage;
use App\Engagement\Models\Message;
use App\Enums\Capability;
use App\Integration\Models\ApiToken;
use App\Models\User;
use App\Payments\Models\PaymentInstallment;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * I-01's per-route half of the scope check: `audience:api` (EnsureAudience)
 * already proves the bearer authenticated and, for an ApiToken specifically,
 * already rate-limits and audits the call — this middleware is the one that
 * knows which Capability a given route requires and refuses a token that
 * was never scoped to it.
 *
 * Two kinds of principal carry a scope: an ApiToken (I-01), checked against
 * its own `scopes` column, or — N-04 — a staff User authenticated by their
 * own OAuth token, checked against that token's Sanctum abilities instead
 * (tokenCan(), not ApiToken::allows(): the grant lives on the token
 * OAuthAuthorizationController minted, not on the user row). A guest token
 * minted on a Contact (G-01) never reaches a route that declares a scope at
 * all, so it falls through to the default and is refused.
 */
class EnsureApiTokenScope
{
    /** Several capabilities are an any-of: a route two kinds of token reach for two different reasons. */
    public function handle(Request $request, Closure $next, string ...$capabilities): Response
    {
        $required = array_map(
            fn (string $capability) => Capability::tryFrom($capability)
                ?? throw new InvalidArgumentException("Unknown capability scope \"{$capability}\"."),
            $capabilities,
        );

        $principal = $request->user('api');

        $allowed = match (true) {
            $principal instanceof ApiToken => collect($required)->contains(fn (Capability $c) => $principal->allows($c)),
            $principal instanceof User => collect($required)->contains(fn (Capability $c) => $principal->tokenCan($c->value) && $principal->role->allows($c)),
            default => false,
        };

        // Was a Contributor's token for a project nobody crewed them on; the
        // admin twin of every one of these routes goes through ClientCasePolicy.
        if ($allowed && $principal instanceof User) {
            $allowed = $this->mayReachBoundCases($principal, $request);
        }

        if (! $allowed) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return $next($request);
    }

    /** Capability is settled by now; what is left is the crew rule ClientCasePolicy::view adds. */
    private function mayReachBoundCases(User $user, Request $request): bool
    {
        foreach ($request->route()->parameters() as $bound) {
            $case = $this->caseOf($bound);

            if ($case !== null && ! $user->can('view', $case)) {
                return false;
            }
        }

        return true;
    }

    private function caseOf(mixed $bound): ?ClientCase
    {
        return match (true) {
            $bound instanceof ClientCase => $bound,
            $bound instanceof CrewAssignment, $bound instanceof TimelineEntry, $bound instanceof ShotListItem => $bound->shoot->case,
            $bound instanceof Message, $bound instanceof InboundMessage => $bound->thread->case,
            $bound instanceof FormInstanceField => $bound->formInstance->case,
            $bound instanceof PaymentInstallment => $bound->case(),
            $bound instanceof Model && method_exists($bound, 'case') => $bound->case,
            default => null,
        };
    }
}
