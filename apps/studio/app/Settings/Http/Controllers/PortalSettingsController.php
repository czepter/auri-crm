<?php

namespace App\Settings\Http\Controllers;

use App\Concerns\AuthorizesStaffSettings;
use App\Http\Controllers\Controller;
use App\Settings\Data\PortalSettingsData;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * T-08's "Portal" pane, mockup 14's own "bald" placeholder finally wired
 * live. One form, one save — like Studio-Profil (T-02) and Zahlungen
 * (T-07), unlike Zahlungsdaten & USt's own split endpoint: nothing here
 * needs a fieldset-level save boundary.
 *
 * The maintenance switch is not a third A-04 revocation path — it never
 * touches `project_contacts` or `contacts.portal_access_revoked_at`, only
 * this table's own `portal_maintenance_enabled`/`_message` columns, read by
 * EnsurePortalMaintenanceClosure alone.
 */
class PortalSettingsController extends Controller
{
    use AuthorizesStaffSettings;

    public function edit(StudioSettingsResolver $settings): Response
    {
        $this->authorizeSettings();

        return Inertia::render('Settings/Portal', [
            'settings' => PortalSettingsData::fromResolver($settings),
        ]);
    }

    public function update(Request $request, StudioSettingsResolver $settings): RedirectResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'portal_maintenance_enabled' => ['required', 'boolean'],
            'portal_maintenance_message' => ['nullable', 'string', 'max:2000'],
            'portal_welcome_text' => ['nullable', 'string', 'max:2000'],
            'portal_legal_datenschutz_url' => ['nullable', 'string', 'max:255', 'url'],
            'portal_legal_agb_url' => ['nullable', 'string', 'max:255', 'url'],
            'portal_accept_quotes_enabled' => ['required', 'boolean'],
            'portal_sign_contracts_enabled' => ['required', 'boolean'],
            'portal_payment_status_enabled' => ['required', 'boolean'],
            'portal_messaging_enabled' => ['required', 'boolean'],
        ]);

        $actor = auth('staff')->user();

        foreach ($validated as $key => $value) {
            $settings->set($key, $value, $actor);
        }

        return redirect('/einstellungen/portal');
    }
}
