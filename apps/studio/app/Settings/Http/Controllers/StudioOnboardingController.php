<?php

namespace App\Settings\Http\Controllers;

use App\Concerns\AuthorizesStaffSettings;
use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Support\StarterTemplateCatalog;
use App\Engagement\Support\InboundAddress;
use App\Http\Controllers\Controller;
use App\Models\UserEmailVerification;
use App\Notifications\NotificationService;
use App\Notifications\Studio\StudioReady;
use App\Settings\Data\PortalSettingsData;
use App\Settings\Data\StudioSettingsData;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
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
            'emailVerified' => auth('staff')->user()->email_verified_at !== null,
            'hasLiveVerification' => UserEmailVerification::forUser(auth('staff')->user()) !== null,
            // T-18: the catalog, minus its blocks — this step chooses, it does
            // not edit, and shipping every starter's document to a screen that
            // renders four words of each would be a page weight nobody asked
            // for. DocumentTemplateController::seedStarters resolves a key
            // back to its blocks server-side.
            'starters' => collect(StarterTemplateCatalog::byType())
                ->map(fn (array $entries) => collect($entries)
                    ->map(fn (array $entry) => Arr::except($entry, 'blocks'))
                    ->all())
                ->all(),
            'starterDefaults' => StarterTemplateCatalog::onboardingDefaults(),
            'typeLabels' => collect(DocumentTemplateType::cases())
                ->mapWithKeys(fn (DocumentTemplateType $type) => [$type->value => $type->label()])
                ->all(),
        ]);
    }

    public function complete(StudioSettingsResolver $settings, NotificationService $notifications): RedirectResponse
    {
        $this->authorizeSettings();

        $owner = auth('staff')->user();

        $settings->set('onboarding_completed_at', now(), $owner);

        $inboundAddress = $settings->inboundSecret() === null
            ? null
            : InboundAddress::catchAll($settings->displayName() ?? '', $settings->inboundSecret());

        $notifications->send($owner, new StudioReady($owner->name, $inboundAddress));

        return redirect('/');
    }
}
