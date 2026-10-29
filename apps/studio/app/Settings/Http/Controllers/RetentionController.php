<?php

namespace App\Settings\Http\Controllers;

use App\Concerns\AuthorizesStaffSettings;
use App\Http\Controllers\Controller;
use App\Models\StudioSettings;
use App\Privacy\Actions\ApplyRetention;
use App\Privacy\Data\RetentionPolicyData;
use App\Privacy\Support\RetentionCandidates;
use App\Settings\Actions\UpdateStudioSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** #501: Frist und Wahl der automatischen Löschung; der tägliche Lauf ist EraseExpiredInquiries. */
class RetentionController extends Controller
{
    use AuthorizesStaffSettings;

    public function show(): Response
    {
        $this->authorizeSettings();

        return Inertia::render('Settings/Retention', [
            'months' => StudioSettings::current()->retention_months,
            'policy' => RetentionPolicyData::current(),
            'candidates' => RetentionCandidates::preview(),
            'funnel' => RetentionCandidates::funnel(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeSettings();

        $this->save($request);

        return back();
    }

    public function run(Request $request, ApplyRetention $apply): RedirectResponse
    {
        $this->authorizeSettings();

        $request->validate([
            'retention_months' => ['required'],
            'current_password' => ['required', 'current_password:staff'],
        ]);

        $months = $this->save($request);
        $apply($months, $request->user('staff'));

        return back();
    }

    private function save(Request $request): ?int
    {
        $validated = $request->validate([
            'retention_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'policy' => ['required_with:retention_months', 'array'],
            ...array_fill_keys(array_map(fn ($part) => "policy.{$part}", ['name', 'text', 'messages', 'offers', 'galleries', 'order']), ['required_with:policy', 'boolean']),
        ]);

        $months = $validated['retention_months'] === null ? null : (int) $validated['retention_months'];

        app(UpdateStudioSetting::class)('retention_months', $months, $request->user('staff'));

        if ($months !== null) {
            app(UpdateStudioSetting::class)('retention_policy', (new RetentionPolicyData(...$validated['policy']))->removed(), $request->user('staff'));
        }

        return $months;
    }
}
