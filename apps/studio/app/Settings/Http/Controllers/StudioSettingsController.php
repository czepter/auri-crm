<?php

namespace App\Settings\Http\Controllers;

use App\Enums\Capability;
use App\Http\Controllers\Controller;
use App\Settings\Data\StudioSettingsData;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The section shell T-00 mocked as screen 14. One live route today —
 * Studio-Profil, carrying only the display_name scaffolding column — with
 * enough save and dirty-state plumbing for T-02 to extend rather than
 * rebuild. Zahlungsdaten & USt and every group below it render as disabled
 * "bald" nav entries: AdminLayout's own sidebar already rules out a link
 * with nothing behind it, and the same call applies one level down.
 */
class StudioSettingsController extends Controller
{
    public function edit(StudioSettingsResolver $settings): Response
    {
        $this->authorizeSettings();

        return Inertia::render('Settings/Index', [
            'settings' => new StudioSettingsData(
                id: $settings->publicId(),
                displayName: $settings->displayName(),
            ),
        ]);
    }

    public function update(Request $request, StudioSettingsResolver $settings): RedirectResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'display_name' => ['nullable', 'string', 'max:255'],
        ]);

        $settings->set('display_name', $validated['display_name'] ?: null, auth('staff')->user());

        return redirect('/einstellungen');
    }

    /**
     * A 404, not a 403 — T-01's issue asks for the same audience-shaped
     * "does not exist" response EnsureAudience already gives a client who
     * reaches for a staff route, deliberately different from every other
     * capability-gated controller in this codebase (ContractTemplatePolicy,
     * InvitationController's authorizeStaffManagement, both 403). Those
     * routes benefit a denied role by existing openly — a photographer who
     * cannot manage contracts still learns the screen is there. Nobody
     * without ManageSettings has a reason to learn the studio's bank details
     * live behind this one at all.
     */
    private function authorizeSettings(): void
    {
        abort_unless(
            auth('staff')->user()?->role->allows(Capability::ManageSettings) ?? false,
            404,
        );
    }
}
