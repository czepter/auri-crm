<?php

namespace App\Settings\Http\Controllers;

use App\Concerns\AuthorizesStaffSettings;
use App\Http\Controllers\Controller;
use App\Settings\Actions\ApplyRetention;
use App\Settings\Data\RetentionPolicyData;
use App\Settings\Support\RetentionCandidates;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** #501: Frist und Wahl der automatischen Löschung; der tägliche Lauf ist EraseExpiredInquiries. */
class RetentionController extends Controller
{
    use AuthorizesStaffSettings;

    public function show(StudioSettingsResolver $settings): Response
    {
        $this->authorizeSettings();

        return Inertia::render('Settings/Retention', [
            'months' => $settings->retentionMonths(),
            'policy' => $settings->retentionPolicy(),
            'candidates' => RetentionCandidates::preview(),
            'funnel' => RetentionCandidates::funnel(),
        ]);
    }

    public function update(Request $request, StudioSettingsResolver $settings): RedirectResponse
    {
        $this->authorizeSettings();

        $this->save($request, $settings);

        return back();
    }

    public function run(Request $request, StudioSettingsResolver $settings, ApplyRetention $apply): RedirectResponse
    {
        $this->authorizeSettings();

        $request->validate([
            'retention_months' => ['required'],
            'current_password' => ['required', 'current_password:staff'],
        ]);

        $months = $this->save($request, $settings);
        $apply($months, $request->user('staff'));

        return back();
    }

    private function save(Request $request, StudioSettingsResolver $settings): ?int
    {
        $validated = $request->validate([
            'retention_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'policy' => ['required_with:retention_months', 'array'],
            ...array_fill_keys(array_map(fn ($part) => "policy.{$part}", ['name', 'text', 'messages', 'offers', 'galleries', 'order']), ['required_with:policy', 'boolean']),
        ]);

        $months = $validated['retention_months'] === null ? null : (int) $validated['retention_months'];

        $settings->set('retention_months', $months, $request->user('staff'));

        if ($months !== null) {
            $settings->set('retention_policy', (new RetentionPolicyData(...$validated['policy']))->removed(), $request->user('staff'));
        }

        return $months;
    }
}
