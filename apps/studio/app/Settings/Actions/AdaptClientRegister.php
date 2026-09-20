<?php

namespace App\Settings\Actions;

use App\Automation\Models\AutomationRule;
use App\Documents\Models\DocumentTemplate;
use App\Documents\Support\StarterBlockSerializer;
use App\Documents\Support\StarterTemplateCatalog;

/**
 * Stellt Standard-Automationen und Startvorlagen auf die andere Anrede um.
 * Nur eine Zeile, deren Text noch dem ausgelieferten Text im alten Ton
 * entspricht, wird angefasst; alles, was das Studio geändert hat, bleibt und
 * wird als übersprungen gezählt. Eine Zeile, die schon im neuen Ton steht,
 * zählt nicht mit.
 */
class AdaptClientRegister
{
    /** @return array{adapted: int, skipped: int} */
    public function __invoke(string $to, bool $dryRun = false): array
    {
        $from = $to === 'formal' ? 'informal' : 'formal';
        $counts = ['adapted' => 0, 'skipped' => 0];

        foreach (config('automation.rules') as $entry) {
            $toned = array_filter($entry, 'is_array');
            $rule = $toned === [] ? null : AutomationRule::query()->where('key', $entry['key'])->first();

            if ($rule === null) {
                continue;
            }

            $old = array_map(fn (array $texts) => $texts[$from], $toned);
            $new = array_map(fn (array $texts) => $texts[$to], $toned);

            $this->settle($counts, $rule->only(array_keys($toned)), $old, $new, $dryRun, fn () => $rule->update($new));
        }

        $target = collect(StarterTemplateCatalog::all($to))->keyBy('key');

        foreach (StarterTemplateCatalog::all($from) as $entry) {
            $blocks = $target[$entry['key']]['blocks'];

            if ($blocks === $entry['blocks']) {
                continue;
            }

            $template = DocumentTemplate::query()->current()->where('type', $entry['type'])->where('name', $entry['name'])->first();

            if ($template === null) {
                continue;
            }

            $this->settle($counts, ['blocks' => $template->blocks], ['blocks' => $entry['blocks']], ['blocks' => $blocks], $dryRun, fn () => $template->newVersion([
                'name' => $template->name,
                'body' => StarterBlockSerializer::toHtml($blocks),
                'blocks' => $blocks,
            ]));
        }

        return $counts;
    }

    private function settle(array &$counts, array $current, array $old, array $new, bool $dryRun, callable $apply): void
    {
        if ($current == $new) {
            return;
        }

        if ($current != $old) {
            $counts['skipped']++;

            return;
        }

        $counts['adapted']++;

        if (! $dryRun) {
            $apply();
        }
    }
}
