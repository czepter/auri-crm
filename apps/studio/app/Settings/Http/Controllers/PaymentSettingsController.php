<?php

namespace App\Settings\Http\Controllers;

use App\Concerns\AuthorizesStaffSettings;
use App\Http\Controllers\Controller;
use App\Settings\Actions\UpdatePaymentSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Saves the Stripe section of the Zahlung page; the page itself is StudioSettingsController's. */
class PaymentSettingsController extends Controller
{
    use AuthorizesStaffSettings;

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

        return back();
    }
}
