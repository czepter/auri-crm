<?php

namespace App\Settings\Http\Controllers;

use App\Concerns\AuthorizesStaffSettings;
use App\Http\Controllers\Controller;
use App\Settings\Data\PortalSettingsData;
use App\Settings\Data\StudioSettingsData;
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
            // The pane's preview draws the portal head, which is the
            // studio's logo, pair and accent — none of which belongs in
            // PortalSettingsData, all of which this DTO already carries.
            'branding' => StudioSettingsData::fromResolver($settings),
        ]);
    }

    public function update(Request $request, StudioSettingsResolver $settings): RedirectResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'portal_maintenance_enabled' => ['sometimes', 'required', 'boolean'],
            'portal_maintenance_message' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'portal_welcome_text' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'portal_legal_datenschutz_url' => ['sometimes', 'nullable', 'string', 'max:255', 'url'],
            'portal_legal_agb_url' => ['sometimes', 'nullable', 'string', 'max:255', 'url'],
            'portal_accept_quotes_enabled' => ['sometimes', 'required', 'boolean'],
            'portal_sign_contracts_enabled' => ['sometimes', 'required', 'boolean'],
            'portal_payment_status_enabled' => ['sometimes', 'required', 'boolean'],
            // P-09 (#228): everything Sprint 25's seven portal screens do
            // that T-08's four switches never described.
            'portal_messaging_mode' => ['sometimes', 'required', 'in:off,booking,any'],
            'portal_welcome_inquiry' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'portal_welcome_many' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'dsgvo_request_recipient' => ['sometimes', 'nullable', 'email', 'max:255'],
            'dsgvo_request_deadline_days' => ['sometimes', 'required', 'integer', 'min:1', 'max:365'],
            'portal_contact_phone' => ['sometimes', 'nullable', 'string', 'max:64'],
            'portal_contact_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'portal_contact_response_time' => ['sometimes', 'nullable', 'string', 'max:120'],
        ]);

        $actor = auth('staff')->user();

        foreach ($validated as $key => $value) {
            $settings->set($key, $value, $actor);
        }

        return redirect('/einstellungen/portal');
    }
}
