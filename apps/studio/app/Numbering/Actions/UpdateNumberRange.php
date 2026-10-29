<?php

namespace App\Numbering\Actions;

use App\Models\User;
use App\Numbering\Enums\NumberRangeResetRule;
use App\Numbering\Models\NumberRange;
use App\Support\AuditLogger;
use Illuminate\Validation\ValidationException;

/**
 * Writes a range's prefix, reset rule and starting counter, and leaves an
 * audit row naming the actor and every changed value — the same convention
 * App\Settings\Actions\UpdateStudioSetting (T-01) established for this section.
 *
 * The one rule with teeth: starting_counter is REFUSED, not silently
 * ignored, the moment the range has issued a number for its current period
 * (NumberRange::hasIssued(), checked against the range's rule as it stands
 * right now — before this write's own reset_rule change, if any, takes
 * effect). A German Nummernkreis must be gapless; once a document carries a
 * number, nothing about where the sequence starts is negotiable again this
 * period. Prefix and reset rule carry no such gate — T-04's issue asks only
 * that changing either warns the studio it applies to documents not yet
 * issued, informational rather than blocking, the settings screen's job
 * rather than this action's.
 */
class UpdateNumberRange
{
    /**
     * @param  array{prefix: string, reset_rule: string, starting_counter: int}  $attributes
     */
    public function __invoke(NumberRange $range, array $attributes, ?User $actor = null): void
    {
        $resetRule = NumberRangeResetRule::from($attributes['reset_rule']);
        $startingCounter = (int) $attributes['starting_counter'];

        if ($startingCounter !== $range->starting_counter && $range->hasIssued()) {
            throw ValidationException::withMessages([
                'starting_counter' => 'Für diesen Zeitraum wurde bereits eine Nummer vergeben. Der Zähler kann jetzt nicht mehr geändert werden.',
            ]);
        }

        $changes = array_filter([
            'prefix' => $attributes['prefix'] === $range->prefix ? null : [$range->prefix, $attributes['prefix']],
            'reset_rule' => $resetRule === $range->reset_rule ? null : [$range->reset_rule->value, $resetRule->value],
            'starting_counter' => $startingCounter === $range->starting_counter ? null : [$range->starting_counter, $startingCounter],
        ]);

        if ($changes === []) {
            return;
        }

        $range->update([
            'prefix' => $attributes['prefix'],
            'reset_rule' => $resetRule,
            'starting_counter' => $startingCounter,
        ]);

        AuditLogger::record(
            event: 'number_range.updated',
            actor: $actor,
            context: ['document_type' => $range->document_type->value, 'changes' => $changes],
            subject: $range->public_id,
        );
    }
}
