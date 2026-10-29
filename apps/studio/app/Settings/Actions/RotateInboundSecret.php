<?php

namespace App\Settings\Actions;

use App\Enums\Capability;
use App\Models\StudioSettings;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * E-12: replace the secret every inbound address is minted from.
 *
 * ### The Inhaber's, and nothing else's
 *
 * Rotating invalidates every address the studio has ever handed out —
 * including the Reply-To on mail already sitting in clients' inboxes. That
 * is the correct behaviour for a leaked address and the wrong thing to
 * discover by accident, so the trigger is narrower than the screen it sits
 * on.
 *
 * **The capability is checked here, in the action.** `AuthorizesStaffSettings`
 * already gates the settings screen, but a gate on a screen is not a gate on
 * an operation: a second route reaching this later would arrive ungated, and
 * this is not an operation to leave depending on where it was called from.
 * `ManageSettings` is Admin-only (see App\Enums\StaffRole::capabilities()).
 *
 * **Staff surface only.** No `api` route calls this, no `I-01` token scope
 * grants it, and no `N-03` MCP tool exposes it. I-01's own rule is that a
 * token cannot be granted what no staff role can do; this is the narrower
 * case — something a staff role can do that no machine credential may. An
 * assistant one confused turn from severing every client's reply path is not
 * a risk worth a convenience.
 *
 * Audited, because "who rotated this, and when" is the one question asked
 * afterwards by whoever is wondering why replies stopped threading.
 */
class RotateInboundSecret
{
    public function __invoke(?User $actor): string
    {
        if (! ($actor?->can(Capability::ManageSettings->value) ?? false)) {
            throw new HttpException(403, 'Only the Inhaber may rotate the inbound address secret.');
        }

        $secret = Str::random(48);

        $settings = StudioSettings::query()->firstOrFail();
        $settings->forceFill([
            'inbound_secret' => $secret,
            'inbound_secret_rotated_at' => now(),
        ])->save();

        AuditLogger::record(
            event: 'settings.inbound_secret_rotated',
            actor: $actor,
            // Never the secret itself, and never the old one: an audit row is
            // read by more people than hold the credential.
            context: ['rotated_at' => now()->toIso8601String()],
        );

        return $secret;
    }
}
