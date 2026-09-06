<?php

namespace App\Automation\Http\Controllers;

use App\Automation\Data\AutomationRuleData;
use App\Automation\Data\AutomationRunData;
use App\Automation\Enums\AutomationAnchor;
use App\Automation\Enums\AutomationCondition;
use App\Automation\Enums\AutomationOffsetDirection;
use App\Automation\Enums\AutomationRecipient;
use App\Automation\Enums\AutomationTriggerType;
use App\Automation\Models\AutomationRule;
use App\Concerns\AuthorizesStaffSettings;
use App\Documents\Support\MergeFieldVocabulary;
use App\Http\Controllers\Controller;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Visus\Cuid2\Cuid2;

/**
 * E-14: the screen E-08's rules never had. Until this controller they could
 * only be created by SeedDefaultAutomationRulesCommand, which meant a studio
 * could read its own automation in config and nowhere else.
 */
class AutomationRuleController extends Controller
{
    use AuthorizesStaffSettings;

    public function index(StudioSettingsResolver $settings): Response
    {
        $this->authorizeSettings();

        return Inertia::render('Settings/Automations', [
            'rules' => AutomationRuleData::collect(
                AutomationRule::query()->orderBy('name')->get()->map(AutomationRuleData::fromModel(...))
            ),
            // Der Sweep laeuft in Studiozeit, nicht in Containerzeit. Wer
            // die Spalte "letzter Lauf" liest, liest sie auf dieser Uhr.
            'timezone' => $settings->timezone(),
        ]);
    }

    public function edit(AutomationRule $automationRule): Response
    {
        $this->authorizeSettings();

        return Inertia::render('Settings/AutomationRule', [
            'rule' => AutomationRuleData::fromModel($automationRule),
            'anchors' => $this->options(AutomationAnchor::cases()),
            'recipients' => $this->options(AutomationRecipient::cases()),
            'conditions' => $this->options(AutomationCondition::cases()),
            // The same vocabulary and the same sample values the document
            // templates show, so a field means one thing in both places.
            'mergeFields' => collect(MergeFieldVocabulary::samples())
                ->map(fn (string $sample, string $path) => ['path' => $path, 'sample' => $sample])
                ->values()
                ->all(),
            'runs' => AutomationRunData::collect(
                $automationRule->runs()->with('anchorable')->latest('fired_at')->limit(20)->get()
                    ->map(AutomationRunData::fromModel(...))
            ),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeSettings();

        $rule = AutomationRule::create([
            ...$this->validated($request, AutomationTriggerType::DateOffset),
            // The key is the seeder's join column, never a studio's to pick.
            'key' => 'custom-'.(new Cuid2(12)),
            'trigger_type' => AutomationTriggerType::DateOffset,
        ]);

        return redirect("/einstellungen/automationen/{$rule->public_id}");
    }

    public function update(Request $request, AutomationRule $automationRule): RedirectResponse
    {
        $this->authorizeSettings();

        $automationRule->update($this->validated($request, $automationRule->trigger_type));

        return back();
    }

    public function destroy(AutomationRule $automationRule): RedirectResponse
    {
        $this->authorizeSettings();

        $automationRule->delete();

        return redirect('/einstellungen/automationen');
    }

    /**
     * An event rule carries no anchor and no offset — AutomationRule::booted()
     * refuses one that does, so requiring them here would make the seeded
     * Buchungsbestaetigung unsaveable from its own screen.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request, AutomationTriggerType $trigger): array
    {
        $dated = $trigger === AutomationTriggerType::DateOffset;

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'anchor' => [Rule::requiredIf($dated), Rule::enum(AutomationAnchor::class)],
            'offset_days' => [Rule::requiredIf($dated), 'integer', 'between:0,365'],
            'direction' => [Rule::requiredIf($dated), Rule::enum(AutomationOffsetDirection::class)],
            'recipient_type' => ['required', Rule::enum(AutomationRecipient::class)],
            'condition' => ['nullable', Rule::enum(AutomationCondition::class)],
            'notification_subject' => ['required', 'string', 'max:255'],
            'notification_body' => ['required', 'string', 'max:5000'],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    /**
     * @param  list<AutomationAnchor|AutomationRecipient|AutomationCondition>  $cases
     * @return list<array{value: string, label: string}>
     */
    private function options(array $cases): array
    {
        return array_map(fn ($case) => ['value' => $case->value, 'label' => $case->label()], $cases);
    }
}
