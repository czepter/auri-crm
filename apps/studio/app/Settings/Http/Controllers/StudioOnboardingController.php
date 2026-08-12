<?php

namespace App\Settings\Http\Controllers;

use App\Concerns\AuthorizesStaffSettings;
use App\Http\Controllers\Controller;
use App\Settings\Data\PortalSettingsData;
use App\Settings\Data\StudioSettingsData;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The five-step first-run wizard. Each step posts to the settings endpoint
 * that already exists (StudioSettingsController, PortalSettingsController) —
 * this controller only shows the shell and stamps completion; no validation
 * or persistence is duplicated here. See RequireStudioOnboarding for the
 * gate this route sits behind (and outside of).
 */
class StudioOnboardingController extends Controller
{
    use AuthorizesStaffSettings;

    public function show(StudioSettingsResolver $settings): Response
    {
        $this->authorizeSettings();

        return Inertia::render('Auth/Onboarding', [
            'settings' => StudioSettingsData::fromResolver($settings),
            'portalSettings' => PortalSettingsData::fromResolver($settings),
        ]);
    }

    public function complete(StudioSettingsResolver $settings): RedirectResponse
    {
        $this->authorizeSettings();

        $settings->set('onboarding_completed_at', now(), auth('staff')->user());

        return redirect('/');
    }
}
