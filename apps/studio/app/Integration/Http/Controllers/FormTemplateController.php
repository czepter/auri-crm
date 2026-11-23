<?php

namespace App\Integration\Http\Controllers;

use App\Crm\Actions\SyncFormTemplateFields;
use App\Crm\Data\FormTemplateData;
use App\Crm\Enums\FormFieldType;
use App\Crm\Models\FormTemplate;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Mirrors App\Crm\Http\Controllers\FormTemplateController on the api surface.
 * The field rules are FormInstanceController's, except options: select only. Editing a template touches no FormInstance:
 * seeding copies.
 */
class FormTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return CrmReadController::page(FormTemplate::active()->with('fields')->withCount('fields'), $request, FormTemplateData::class);
    }

    public function store(Request $request, SyncFormTemplateFields $sync): JsonResponse
    {
        $validated = $request->validate($this->rules($request));

        $template = FormTemplate::create(collect($validated)->except('fields')->all());
        $sync($template, $validated['fields'] ?? []);

        AuditLogger::record(
            event: 'form_template.created',
            actor: $this->actor($request),
            context: ['name' => $template->name],
            subject: $template->public_id,
        );

        return response()->json(['data' => FormTemplateData::fromModel($template->load('fields'))], 201);
    }

    public function update(Request $request, FormTemplate $formTemplate, SyncFormTemplateFields $sync): JsonResponse
    {
        $validated = $request->validate($this->rules($request, $formTemplate));

        $formTemplate->update(collect($validated)->except('fields')->all());
        $sync($formTemplate, $validated['fields']);

        AuditLogger::record(
            event: 'form_template.updated',
            actor: $this->actor($request),
            context: ['name' => $formTemplate->name],
            subject: $formTemplate->public_id,
        );

        return response()->json(['data' => FormTemplateData::fromModel($formTemplate->load('fields'))]);
    }

    private function rules(Request $request, ?FormTemplate $template = null): array
    {
        return [
            'name' => [$template ? 'sometimes' : 'required', 'string', 'max:120', function (string $attribute, mixed $value, $fail) use ($template) {
                $taken = FormTemplate::query()
                    ->whereRaw('lower(name) = ?', [mb_strtolower($value)])
                    ->when($template, fn ($query) => $query->whereKeyNot($template->id))
                    ->exists();

                if ($taken) {
                    $fail(__('validation.unique', ['attribute' => $attribute]));
                }
            }],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'project_type' => ['sometimes', 'nullable', Rule::exists('project_types', 'key')->where('is_active', true)],
            'fields' => [$template ? 'present' : 'sometimes', 'array', 'max:100'],
            'fields.*.id' => ['nullable', 'string', 'distinct'],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.type' => ['required', Rule::enum(FormFieldType::class)],
            'fields.*.required' => ['required', 'boolean'],
            'fields.*.help' => ['nullable', 'string', 'max:500'],
            ...$this->optionRules($request),
        ];
    }

    /**
     * Options are a select's choices here. A file or group_photos field keeps
     * the configuration the builder gave it; SyncFormTemplateFields leaves it alone.
     */
    private function optionRules(Request $request): array
    {
        return collect(array_keys((array) $request->input('fields', [])))
            ->mapWithKeys(fn (int|string $index) => $request->input("fields.{$index}.type") === FormFieldType::Select->value
                ? FormFieldType::Select->optionRules("fields.{$index}")
                : ["fields.{$index}.options" => ['prohibited']])
            ->all();
    }

    private function actor(Request $request): ?User
    {
        $principal = $request->user('api');

        return $principal instanceof User ? $principal : null;
    }
}
