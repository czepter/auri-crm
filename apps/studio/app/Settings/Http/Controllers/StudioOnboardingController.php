<?php

namespace App\Settings\Http\Controllers;

use App\Concerns\AuthorizesStaffSettings;
use App\Documents\Actions\SeedStarterTemplates;
use App\Documents\Support\StarterTemplateCatalog;
use App\Http\Controllers\Controller;
use App\Settings\Data\StudioSettingsData;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The two-step first-run wizard. Each step posts to the settings endpoint
 * that already exists (StudioSettingsController) — this controller only shows
 * the shell, writes the starter templates and stamps completion; no
 * validation or persistence is duplicated here. See RequireStudioOnboarding
 * for the gate this route sits behind (and outside of).
 */
class StudioOnboardingController extends Controller
{
    use AuthorizesStaffSettings;

    public function show(StudioSettingsResolver $settings): Response
    {
        $this->authorizeSettings();

        return Inertia::render('Auth/Onboarding', [
            'settings' => StudioSettingsData::fromResolver($settings),
        ]);
    }

    public function complete(StudioSettingsResolver $settings, SeedStarterTemplates $seed): RedirectResponse
    {
        $this->authorizeSettings();

        $actor = auth('staff')->user();

        // Also on "später abschließen": a studio with no templates can issue
        // nothing. Idempotent, so a second pass writes nothing.
        $seed(StarterTemplateCatalog::onboardingDefaults($settings->vatMode()), $actor);

        $settings->set('onboarding_completed_at', now(), $actor);

        return redirect('/');
    }
}
