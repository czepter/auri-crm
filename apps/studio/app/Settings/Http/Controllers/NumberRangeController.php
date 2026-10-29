<?php

namespace App\Settings\Http\Controllers;

use App\Concerns\AuthorizesStaffSettings;
use App\Http\Controllers\Controller;
use App\Numbering\Actions\AllocateDocumentNumber;
use App\Numbering\Actions\UpdateNumberRange;
use App\Numbering\Enums\NumberRangeResetRule;
use App\Numbering\Models\NumberRange;
use App\Settings\Data\NumberRangeData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * T-04's Nummernkreise screen: one row per DocumentType, each editable and
 * each carrying a preview of the number it would issue next. Sits beside
 * StudioSettingsController under the same /einstellungen shell and the same
 * ManageSettings gate — see AuthorizesStaffSettings for why that gate is a
 * 404, not a 403.
 */
class NumberRangeController extends Controller
{
    use AuthorizesStaffSettings;

    public function index(AllocateDocumentNumber $allocator): Response
    {
        $this->authorizeSettings();

        $ranges = NumberRange::query()->orderBy('document_type')->get();

        return Inertia::render('Settings/Numbering', [
            'ranges' => $ranges->map(fn (NumberRange $range) => NumberRangeData::fromModel($range, $allocator))->all(),
        ]);
    }

    public function update(Request $request, NumberRange $numberRange, UpdateNumberRange $updateNumberRange): RedirectResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'prefix' => ['required', 'string', 'max:8', 'regex:/^[A-Z]+$/'],
            'reset_rule' => ['required', Rule::enum(NumberRangeResetRule::class)],
            'starting_counter' => ['required', 'integer', 'min:1'],
        ]);

        $updateNumberRange($numberRange, $validated, auth('staff')->user());

        return redirect('/einstellungen/nummernkreise');
    }
}
