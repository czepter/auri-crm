<?php

namespace App\Settings\Http\Controllers;

use App\Automation\Actions\SeedDefaultAutomationRules;
use App\Concerns\AuthorizesStaffSettings;
use App\Documents\Actions\SeedStarterTemplates;
use App\Documents\Support\StarterTemplateCatalog;
use App\Engagement\Support\InboundAddress;
use App\Http\Controllers\Controller;
use App\Models\StudioSettings;
use App\Models\UserEmailVerification;
use App\Notifications\NotificationService;
use App\Settings\Actions\UpdateStudioSetting;
use App\Settings\Data\StudioSettingsData;
use App\Staff\Notifications\StudioReady;
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

    public function show(): Response|RedirectResponse
    {
        $this->authorizeSettings();

        if (StudioSettings::current()->onboardingCompleted()) {
            return redirect('/einstellungen/allgemein');
        }

        return Inertia::render('Auth/Onboarding', [
            'settings' => StudioSettingsData::current(),
            'emailVerified' => auth('staff')->user()->email_verified_at !== null,
            'hasLiveVerification' => UserEmailVerification::forUser(auth('staff')->user()) !== null,
        ]);
    }

    public function complete(
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
        $seed(StarterTemplateCatalog::onboardingDefaults(), $owner, StudioSettings::current()->client_register);
        $seedRules();

        app(UpdateStudioSetting::class)('onboarding_completed_at', now(), $owner);

        $inboundAddress = StudioSettings::current()->inbound_secret === null
            ? null
            : InboundAddress::catchAll(StudioSettings::current()->display_name ?? '', StudioSettings::current()->inbound_secret);

        $notifications->send($owner, new StudioReady($owner->name, $inboundAddress));

        return redirect('/');
    }
}
