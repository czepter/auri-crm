<?php

namespace App\Integration\Http\Controllers;

use App\Crm\Actions\SeedFormInstanceFromTemplate;
use App\Crm\Data\FormInstanceData;
use App\Crm\Enums\FormFieldType;
use App\Crm\Models\ClientCase;
use App\Crm\Models\FormInstance;
use App\Crm\Models\FormInstanceField;
use App\Crm\Models\FormTemplate;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Mirrors App\Crm\Http\Controllers\FormInstanceController's store, update,
 * share and answer — the same SeedFormInstanceFromTemplate, the same
 * wholesale-replace on update. No upload() here: an api-surface caller has
 * no way to attach bytes to the request, so a "Datei" field's answer stays
 * out of scope, as it is on every other api-surface write.
 */
class FormInstanceController extends Controller
{
    public function index(ClientCase $case): JsonResponse
    {
        $instances = $case->formInstances()->with('fields.currentAnswer')->get();

        return response()->json(['data' => $instances->map(FormInstanceData::fromModel(...))->all()]);
    }

    public function store(Request $request, ClientCase $case, SeedFormInstanceFromTemplate $seed): JsonResponse
    {
        $validated = $request->validate([
            'template' => ['nullable', 'string', Rule::exists('form_templates', 'public_id')->where('is_active', true)],
        ]);

        $template = ! empty($validated['template'])
            ? FormTemplate::active()->where('public_id', $validated['template'])->firstOrFail()
            : null;

        $instance = $case->formInstances()->create([
            'name' => $template?->name ?? 'Neuer Fragebogen',
            'source_template_name' => $template?->name,
        ]);

        if ($template) {
            $seed($instance, $template);
        }

        return response()->json(['data' => FormInstanceData::fromModel($instance->load('fields.currentAnswer'))], 201);
    }

    public function update(Request $request, FormInstance $formInstance): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'fields' => ['array'],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.type' => ['required', Rule::enum(FormFieldType::class)],
            'fields.*.required' => ['required', 'boolean'],
            ...$this->optionRules($request),
        ]);

        $formInstance->update(['name' => $validated['name']]);

        $formInstance->fields()->delete();
        $formInstance->fields()->createMany($this->fieldRows($validated['fields']));

        return response()->json(['data' => FormInstanceData::fromModel($formInstance->load('fields.currentAnswer'))]);
    }

    public function share(Request $request, FormInstance $formInstance): JsonResponse
    {
        $shared = $request->validate(['shared' => ['required', 'boolean']])['shared'];

        $formInstance->forceFill(['shared_at' => $shared ? now() : null])->save();

        return response()->json(['data' => FormInstanceData::fromModel($formInstance->load('fields.currentAnswer'))]);
    }

    public function answer(Request $request, FormInstanceField $formInstanceField): JsonResponse
    {
        abort_if($formInstanceField->type === FormFieldType::File, 422, 'A file field cannot be answered over the api.');

        $validated = $request->validate(['value' => ['nullable', 'string', 'max:20000']]);

        $value = $formInstanceField->type === FormFieldType::GroupPhotos
            ? FormInstanceField::normaliseGroupPhotos($validated['value'] ?? null)
            : $validated['value'] ?? null;

        $formInstanceField->answer($value);

        return response()->json(['data' => FormInstanceData::fromModel($formInstanceField->formInstance->load('fields.currentAnswer'))]);
    }

    /** Same shape App\Crm\Http\Controllers\FormInstanceController::optionRules() validates with. */
    private function optionRules(Request $request): array
    {
        $fields = (array) $request->input('fields', []);

        return collect(array_keys($fields))
            ->mapWithKeys(function (int|string $index) use ($request) {
                $type = $request->input("fields.{$index}.type");

                return (FormFieldType::tryFrom(is_string($type) ? $type : '') ?? FormFieldType::Text)
                    ->optionRules("fields.{$index}");
            })
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function fieldRows(array $fields): array
    {
        return collect($fields)->sortKeys()->values()->map(fn (array $field, int $index) => [
            'label' => $field['label'],
            'type' => $field['type'],
            'required' => $field['required'],
            'options' => $field['options'] ?? null,
            'sort_order' => $index,
        ])->all();
    }
}
