<?php

namespace App\Documents\Actions;

use App\Documents\Models\DocumentTemplate;
use App\Documents\Support\StarterBlockSerializer;
use App\Documents\Support\StarterTemplateCatalog;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * T-18: write the chosen starters as ordinary tenant templates.
 *
 * Goes through `DocumentTemplate::createFirstVersion()`, the same path the
 * create screen uses. Not a `DatabaseSeeder` addition — the production seeding
 * hole the first-run plan closed stays closed, and a seeder would hold a
 * second opinion about what a template is.
 *
 * ### Idempotent without remembering
 *
 * T-16 is explicit that nothing records which starter a template came from, so
 * this cannot dedupe on provenance. It dedupes on what a studio would actually
 * see as a duplicate: a **current template of the same type with the same
 * name**. Re-entering the wizard step therefore adds nothing, and a studio
 * that renamed its quote gets no second one imposed on it either — their
 * own row wins, and nothing here overwrites it.
 *
 * @return int how many were written
 */
class SeedStarterTemplates
{
    /** @param list<string> $keys */
    public function __invoke(array $keys, ?User $actor, string $register = 'formal'): int
    {
        $entries = collect($keys)
            ->map(fn (string $key) => StarterTemplateCatalog::find($key, $register))
            ->filter()
            ->values();

        if ($entries->isEmpty()) {
            return 0;
        }

        return DB::transaction(function () use ($entries, $actor) {
            $written = 0;

            foreach ($entries as $entry) {
                $exists = DocumentTemplate::query()
                    ->current()
                    ->where('type', $entry['type'])
                    ->where('name', $entry['name'])
                    ->exists();

                if ($exists) {
                    continue;
                }

                $template = DocumentTemplate::createFirstVersion([
                    'type' => $entry['type'],
                    'shoot_kind' => $entry['shootKind'],
                    'name' => $entry['name'],
                    'body' => StarterBlockSerializer::toHtml($entry['blocks']),
                    'blocks' => $entry['blocks'],
                ]);

                AuditLogger::record(
                    event: 'document_template.seeded',
                    actor: $actor,
                    // The starter key is context on the event, not a column on
                    // the template: it says what was accepted and by whom,
                    // without giving a later release something to reach back
                    // through.
                    context: ['name' => $template->name, 'type' => $template->type->value, 'starter' => $entry['key']],
                    subject: $template->public_id,
                );

                $written++;
            }

            return $written;
        });
    }
}
