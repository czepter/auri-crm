<?php

namespace App\Settings\Http\Controllers;

use App\Concerns\AuthorizesStaffSettings;
use App\Http\Controllers\Controller;
use App\Settings\Actions\UpdatePaymentSettings;
use App\Settings\Data\PaymentSettingsData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * T-07's "Zahlungen" pane — Stripe as an optional capability plus the
 * reminders default. Deposit percentage and Zahlungsziel, the other two
 * provider-independent defaults T-07's own issue named, are edited on T-05's
 * Dokumente pane instead (studio_settings.deposit_percentage and
 * .payment_term_days) — T-05 landed first, so this pane reads them there
 * rather than growing a second, conflicting editable copy; see
 * PaymentSettingsData. Sits beside StudioSettingsController and
 * NumberRangeController under the same /einstellungen shell and the same
 * ManageSettings gate (see AuthorizesStaffSettings for why that gate is a
 * 404, not a 403).
 *
 * One form, one save — like Studio-Profil (T-02), unlike Zahlungsdaten &
 * USt's own split endpoint: nothing on this pane needs a fieldset-level save
 * boundary the way two mockup-drawn forms on one screen do.
 */
class PaymentSettingsController extends Controller
{
    use AuthorizesStaffSettings;

    public function edit(): Response
    {
        $this->authorizeSettings();

        return Inertia::render('Settings/Payments', [
            'settings' => PaymentSettingsData::current(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'payments_stripe_enabled' => ['required', 'boolean'],
            'payments_stripe_mode' => ['required', 'in:test,live'],
            // Write-only: absent or blank leaves the stored pair alone. Never
            // echoed back — see App\Settings\Actions\UpdatePaymentSettings.
            'payments_stripe_test_publishable_key' => ['nullable', 'string', 'max:1000'],
            'payments_stripe_test_secret_key' => ['nullable', 'string', 'max:1000'],
            'payments_stripe_live_publishable_key' => ['nullable', 'string', 'max:1000'],
            'payments_stripe_live_secret_key' => ['nullable', 'string', 'max:1000'],
        ]);

        app(UpdatePaymentSettings::class)($validated, auth('staff')->user());

        return redirect('/einstellungen/zahlungen');
    }
}
