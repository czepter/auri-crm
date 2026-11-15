<?php

namespace App\Crm\Actions;

use App\Crm\Models\FormInstance;
use App\Crm\Models\FormInstanceField;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * A row matched by public id and type is updated in place, so the answers and
 * files hanging off it survive a save. Anything else is a new question, and an
 * existing field no row matched is removed with its answers and file bytes.
 */
class SyncFormInstanceFields
{
    /** @param  list<array<string, mixed>>  $fields */
    public function __invoke(FormInstance $instance, array $fields): void
    {
        $existing = $instance->fields()->get()->keyBy('public_id');
        $kept = [];
        $paths = [];

        DB::transaction(function () use ($instance, $fields, $existing, &$kept, &$paths) {
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

                if ($field instanceof FormInstanceField && $field->type->value === $row['type']) {
                    $field->update($attributes);
                    $kept[] = $field->id;
                } else {
                    $instance->fields()->create($attributes);
                }
            }

            $removed = $existing->reject(fn (FormInstanceField $field) => in_array($field->id, $kept, true));
            $paths = $removed->flatMap(fn (FormInstanceField $field) => $field->files()->pluck('path'))->all();
            $instance->fields()->whereIn('id', $removed->pluck('id'))->delete();
        });

        Storage::disk('local')->delete($paths);
    }
}
