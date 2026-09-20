<?php

namespace App\Settings\Http\Controllers;

use App\Automation\Actions\SeedDefaultAutomationRules;
use App\Concerns\AuthorizesStaffSettings;
use App\Documents\Actions\SeedStarterTemplates;
use App\Documents\Support\StarterTemplateCatalog;
use App\Engagement\Support\InboundAddress;
use App\Http\Controllers\Controller;
use App\Models\UserEmailVerification;
use App\Notifications\NotificationService;
use App\Notifications\Studio\StudioReady;
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
            'emailVerified' => auth('staff')->user()->email_verified_at !== null,
            'hasLiveVerification' => UserEmailVerification::forUser(auth('staff')->user()) !== null,
        ]);
    }

    public function complete(
        StudioSettingsResolver $settings,
        SeedStarterTemplates $seed,
        SeedDefaultAutomationRules $seedRules,
        NotificationService $notifications,
    ): RedirectResponse {
        $this->authorizeSettings();

        $owner = auth('staff')->user();

        // Also on "später abschließen": a studio with no templates can send
        // nothing. Idempotent, so a second pass writes nothing. The invoice
        // starter is not among them — InvoiceController::create writes it
        // once the VAT mode is answered.
        $seed(StarterTemplateCatalog::onboardingDefaults(), $owner, $settings->clientRegister());
        $seedRules();

        $settings->set('onboarding_completed_at', now(), $owner);

        $inboundAddress = $settings->inboundSecret() === null
            ? null
            : InboundAddress::catchAll($settings->displayName() ?? '', $settings->inboundSecret());

        $notifications->send($owner, new StudioReady($owner->name, $inboundAddress));

        return redirect('/');
    }
}
