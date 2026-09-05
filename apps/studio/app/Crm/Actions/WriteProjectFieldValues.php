<?php

namespace App\Crm\Actions;

use App\Crm\Enums\ProjectFieldType;
use App\Crm\Models\Project;
use App\Crm\Models\ProjectField;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * C-25: the one writer of projects.field_values. The admin screen, the api
 * surface and through it the MCP server all call this, so a value written by
 * an agent obeys the same definition a staff member's save does.
 *
 * Required is enforced here rather than at the project's own creation: an
 * inquiry arrives before anybody knows the guest count, and a type that
 * demands one at capture would push the studio into inventing numbers.
 */
class WriteProjectFieldValues
{
    /** @param  array<string, mixed>  $values */
    public function __invoke(Project $project, array $values, ?User $actor): void
    {
        /** @var Collection<string, ProjectField> $fields */
        $fields = $project->type?->fields()->active()->get()->keyBy('key') ?? collect();

        $this->guardUnknown($values, $fields);

        $clean = [];
        /** @var list<array{ProjectField, list<string>}> $widen */
        $widen = [];

        foreach ($values as $key => $value) {
            $field = $fields[$key];
            $value = $this->cast($field->type, $value);

            $this->validate($field, $value);

            $added = $this->newOptions($field, $value);

            if ($added !== []) {
                $widen[] = [$field, $added];
            }

            $clean[$key] = $value;
        }

        // Merge, never replace: a caller writing one key must not blank the
        // rest, and a value under a key the current type no longer defines
        // stays where it is (a type change is reversible that way).
        $merged = array_merge($project->field_values ?? [], $clean);

        $this->guardRequired($fields, $merged);

        $changed = array_keys(array_filter(
            $clean,
            fn (mixed $value, string $key) => ($project->field_values[$key] ?? null) !== $value,
            ARRAY_FILTER_USE_BOTH,
        ));

        if ($changed === [] && $widen === []) {
            return;
        }

        // Every write together: a payload that fails on its third field must
        // not leave an option the second one grew.
        DB::transaction(function () use ($project, $merged, $changed, $widen, $actor) {
            foreach ($widen as [$field, $added]) {
                $field->update(['options' => array_values(array_merge($field->options ?? [], $added))]);
            }

            $project->forceFill(['field_values' => $merged])->save();

            AuditLogger::record(
                event: 'project.fields_changed',
                actor: $actor,
                context: ['fields' => $changed],
                subject: $project->public_id,
                projectId: $project->id,
            );
        });
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  Collection<string, ProjectField>  $fields
     */
    private function guardUnknown(array $values, Collection $fields): void
    {
        $unknown = array_diff(array_keys($values), $fields->keys()->all());

        if ($unknown !== []) {
            throw ValidationException::withMessages(
                array_reduce(
                    $unknown,
                    fn (array $carry, string $key) => $carry + ["fields.{$key}" => 'Unbekanntes Feld.'],
                    [],
                )
            );
        }
    }

    /** The wire carries strings; the column carries the type the definition names. */
    private function cast(ProjectFieldType $type, mixed $value): mixed
    {
        if ($value === '' || $value === null) {
            return null;
        }

        return match ($type) {
            ProjectFieldType::Number => is_numeric($value) ? $value + 0 : $value,
            ProjectFieldType::Boolean => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $value,
            ProjectFieldType::Tags => is_array($value) ? array_values(array_filter($value, fn ($tag) => $tag !== '' && $tag !== null)) : $value,
            default => $value,
        };
    }

    private function validate(ProjectField $field, mixed $value): void
    {
        if ($value === null) {
            return;
        }

        $rules = ["fields.{$field->key}" => $field->type->valueRules()];

        if ($field->type === ProjectFieldType::Tags) {
            $rules["fields.{$field->key}.*"] = ['string', 'max:255'];
        }

        Validator::make(['fields' => [$field->key => $value]], $rules, [], [
            "fields.{$field->key}" => $field->label,
        ])->validate();

        if (! $field->type->hasOptions() || $field->allow_new_options) {
            return;
        }

        $offered = $field->options ?? [];
        $unknown = array_diff((array) $value, $offered);

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                "fields.{$field->key}" => 'Keine gültige Option: '.implode(', ', $unknown),
            ]);
        }
    }

    /**
     * User story 5: a list the studio marked as growing grows where it is
     * used. What is new about this value, or nothing.
     *
     * @return list<string>
     */
    private function newOptions(ProjectField $field, mixed $value): array
    {
        if ($value === null || ! $field->type->hasOptions() || ! $field->allow_new_options) {
            return [];
        }

        return array_values(array_diff((array) $value, $field->options ?? []));
    }

    /**
     * @param  Collection<string, ProjectField>  $fields
     * @param  array<string, mixed>  $merged
     */
    private function guardRequired(Collection $fields, array $merged): void
    {
        $missing = $fields
            ->filter(fn (ProjectField $field) => $field->required)
            ->filter(fn (ProjectField $field) => in_array($merged[$field->key] ?? null, [null, '', []], true))
            ->mapWithKeys(fn (ProjectField $field) => ["fields.{$field->key}" => "{$field->label} fehlt."]);

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages($missing->all());
        }
    }
}
