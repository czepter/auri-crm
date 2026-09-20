<?php

namespace App\Settings\Http\Controllers;

use App\Concerns\AuthorizesStaffSettings;
use App\Http\Controllers\Controller;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** #501: die Frist für die automatische Löschung; der Lauf ist EraseExpiredInquiries. */
class RetentionController extends Controller
{
    use AuthorizesStaffSettings;

    public function show(StudioSettingsResolver $settings): Response
    {
        $this->authorizeSettings();

        return Inertia::render('Settings/Retention', ['months' => $settings->retentionMonths()]);
    }

    public function update(Request $request, StudioSettingsResolver $settings): RedirectResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate(['retention_months' => ['nullable', 'integer', 'min:1', 'max:120']]);

        $settings->set('retention_months', $validated['retention_months'], $request->user('staff'));

        return back();
    }
}
