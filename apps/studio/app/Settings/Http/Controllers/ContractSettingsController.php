<?php

namespace App\Settings\Http\Controllers;

use App\Concerns\AuthorizesStaffSettings;
use App\Enums\ContractMode;
use App\Http\Controllers\Controller;
use App\Settings\Actions\UpdateStudioSetting;
use App\Settings\Data\ContractSettingsData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ContractSettingsController extends Controller
{
    use AuthorizesStaffSettings;

    public function edit(): Response
    {
        $this->authorizeSettings();

        return Inertia::render('Settings/Contracts', ['settings' => ContractSettingsData::current()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'contract_mode' => ['sometimes', 'required', Rule::enum(ContractMode::class)],
            'portal_sign_contracts_enabled' => ['sometimes', 'required', 'boolean'],
        ]);

        $actor = auth('staff')->user();

        foreach ($validated as $key => $value) {
            app(UpdateStudioSetting::class)($key, $value, $actor);
        }

        return back();
    }
}
