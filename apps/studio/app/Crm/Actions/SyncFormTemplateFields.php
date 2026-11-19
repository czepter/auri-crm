<?php

namespace App\Crm\Actions;

use App\Crm\Models\FormField;
use App\Crm\Models\FormTemplate;
use Illuminate\Support\Facades\DB;

/**
 * A row matched by public id is updated in place, a row without a match is
 * created, and an existing field no row matched is deleted.
 */
class SyncFormTemplateFields
{
    /** @param  list<array<string, mixed>>  $fields */
    public function __invoke(FormTemplate $template, array $fields): void
    {
        $existing = $template->fields()->get()->keyBy('public_id');
        $kept = [];

        DB::transaction(function () use ($template, $fields, $existing, &$kept) {
            // sortKeys because validated() hands the rows back in rule order, not
            // in list order, once a row carries its own type-dependent options rules.
            foreach (collect($fields)->sortKeys()->values() as $index => $row) {
                $attributes = [
                    'label' => $row['label'],
                    'type' => $row['type'],
                    'required' => $row['required'],
                    'help' => filled($row['help'] ?? null) ? trim($row['help']) : null,
                    'options' => $row['options'] ?? null,
                    'sort_order' => $index,
                ];

                $field = $existing->get($row['id'] ?? '');

                if ($field instanceof FormField) {
                    $field->update($attributes);
                    $kept[] = $field->id;
                } else {
                    $template->fields()->create($attributes);
                }
            }

            $template->fields()->whereNotIn('id', $kept)->whereIn('id', $existing->pluck('id'))->delete();
        });
    }
}
