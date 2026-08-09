<?php

namespace App\Settings\Http\Controllers;

use App\Concerns\AuthorizesStaffSettings;
use App\Http\Controllers\Controller;
use App\Settings\Data\PaymentSettingsData;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * T-07's "Zahlungen" pane — Stripe as an optional capability plus the
 * provider-independent deposit, Zahlungsziel and reminder defaults. Sits
 * beside StudioSettingsController and NumberRangeController under the same
 * /einstellungen shell and the same ManageSettings gate (see
 * AuthorizesStaffSettings for why that gate is a 404, not a 403).
 *
 * One form, one save — like Studio-Profil (T-02), unlike Zahlungsdaten &
 * USt's own split endpoint: nothing on this pane needs a fieldset-level save
 * boundary the way two mockup-drawn forms on one screen do.
 */
class PaymentSettingsController extends Controller
{
    use AuthorizesStaffSettings;

    public function edit(StudioSettingsResolver $settings): Response
    {
        $this->authorizeSettings();

        return Inertia::render('Settings/Payments', [
            'settings' => PaymentSettingsData::fromResolver($settings),
        ]);
    }

    public function update(Request $request, StudioSettingsResolver $settings): RedirectResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'payments_stripe_enabled' => ['required', 'boolean'],
            'payments_stripe_mode' => ['required', 'in:test,live'],
            'payments_deposit_percent' => ['required', 'integer', 'min:0', 'max:10000'],
            'payments_zahlungsziel_days' => ['required', 'integer', 'min:0', 'max:365'],
            'payments_reminders_enabled' => ['required', 'boolean'],
            // Write-only: absent or blank leaves the stored pair alone. Never
            // echoed back — see StudioSettingsResolver::setPaymentSettings().
            'payments_stripe_test_publishable_key' => ['nullable', 'string', 'max:1000'],
            'payments_stripe_test_secret_key' => ['nullable', 'string', 'max:1000'],
            'payments_stripe_live_publishable_key' => ['nullable', 'string', 'max:1000'],
            'payments_stripe_live_secret_key' => ['nullable', 'string', 'max:1000'],
        ]);

        $settings->setPaymentSettings($validated, auth('staff')->user());

        return redirect('/einstellungen/zahlungen');
    }
}
