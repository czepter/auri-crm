<?php

namespace App\Documents\Http\Controllers;

use App\Crm\Enums\ShootKind;
use App\Documents\Data\DocumentTemplateData;
use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Models\DocumentTemplate;
use App\Documents\Support\DocumentRenderer;
use App\Documents\Support\MergeFieldResolver;
use App\Documents\Support\MergeFieldVocabulary;
use App\Http\Controllers\Controller;
use App\Settings\Support\StudioSettingsResolver;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD over the current version of every template family, across every
 * App\Documents\Enums\DocumentTemplateType — T-12's generalisation of M-01's
 * ContractTemplateController. "Update" never writes to an existing row —
 * see DocumentTemplate::newVersion() — so a Contract already rendered from
 * an older version keeps pointing at a row this controller never touches
 * again.
 */
class DocumentTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', DocumentTemplate::class);

        $type = $request->query('type');

        $templates = DocumentTemplate::current()
            ->when($type, fn ($query, $type) => $query->where('type', $type))
            ->orderBy('name')
            ->get();

        return Inertia::render('DocumentTemplates/Index', [
            'templates' => DocumentTemplateData::collect($templates),
            'type' => $type,
            'types' => $this->typeOptions(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', DocumentTemplate::class);

        return Inertia::render('DocumentTemplates/Create', [
            'types' => $this->typeOptions(),
            'shootKinds' => $this->shootKindOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', DocumentTemplate::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:100000'],
            'type' => ['required', Rule::enum(DocumentTemplateType::class)],
            'shoot_kind' => ['nullable', Rule::enum(ShootKind::class)],
        ]);

        $template = DocumentTemplate::createFirstVersion($validated);

        AuditLogger::record(
            event: 'document_template.created',
            actor: auth('staff')->user(),
            context: ['name' => $template->name, 'type' => $template->type->value],
            subject: $template->public_id,
        );

        return redirect('/dokumentvorlagen');
    }

    public function edit(DocumentTemplate $documentTemplate): Response
    {
        $this->authorize('update', $documentTemplate);

        // A superseded version is history, not a draft — reached only by a
        // stale link, since the index only ever lists is_current rows.
        abort_unless($documentTemplate->is_current, 404);

        return Inertia::render('DocumentTemplates/Edit', [
            'template' => DocumentTemplateData::fromModel($documentTemplate),
        ]);
    }

    public function update(Request $request, DocumentTemplate $documentTemplate): RedirectResponse
    {
        $this->authorize('update', $documentTemplate);

        abort_unless($documentTemplate->is_current, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:100000'],
        ]);

        // Never $documentTemplate->update(...): this is the seam that keeps
        // every already-rendered document's template row exactly as it was.
        $version = $documentTemplate->newVersion($validated);

        AuditLogger::record(
            event: 'document_template.updated',
            actor: auth('staff')->user(),
            context: ['name' => $version->name, 'type' => $version->type->value, 'version' => $version->version],
            subject: $version->public_id,
        );

        return redirect('/dokumentvorlagen');
    }

    /**
     * Renders $body — saved or not — against MergeFieldVocabulary::sample(),
     * wrapped the same way a real render would wrap it (DocumentRenderer),
     * so a template is checked before it ever reaches a client rather than
     * after. Takes type+body directly rather than a template id: the create
     * and edit screens both call this against whatever is currently typed
     * into the form, published or not.
     */
    public function preview(Request $request, DocumentRenderer $renderer, StudioSettingsResolver $settings): HttpResponse
    {
        $this->authorize('create', DocumentTemplate::class);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:100000'],
            'type' => ['required', Rule::enum(DocumentTemplateType::class)],
        ]);

        $type = DocumentTemplateType::from($validated['type']);
        $resolved = MergeFieldResolver::resolve($validated['body'], MergeFieldVocabulary::sample());
        $html = $renderer->wrap($type, $resolved, $settings);

        return response($html)->header('Content-Type', 'text/html; charset=utf-8');
    }

    /** @return list<array{value: string, label: string}> */
    private function typeOptions(): array
    {
        return collect(DocumentTemplateType::cases())
            ->map(fn (DocumentTemplateType $type) => ['value' => $type->value, 'label' => $type->label()])
            ->all();
    }

    /** Same shape TaskTemplateController already builds for the identical select. */
    private function shootKindOptions(): array
    {
        return collect(ShootKind::cases())
            ->map(fn (ShootKind $kind) => ['value' => $kind->value, 'label' => $kind->label()])
            ->all();
    }
}
