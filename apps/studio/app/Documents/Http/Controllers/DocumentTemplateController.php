<?php

namespace App\Documents\Http\Controllers;

use App\Crm\Enums\ShootKind;
use App\Crm\Models\Project;
use App\Documents\Actions\SeedStarterTemplates;
use App\Documents\Data\DocumentTemplateData;
use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Models\DocumentTemplate;
use App\Documents\Support\DocumentBlockCatalog;
use App\Documents\Support\DocumentMergeDataBuilder;
use App\Documents\Support\DocumentRenderer;
use App\Documents\Support\InlineHtmlSanitizer;
use App\Documents\Support\MergeFieldResolver;
use App\Documents\Support\MergeFieldVocabulary;
use App\Documents\Support\SafeHtml;
use App\Documents\Support\StarterTemplateCatalog;
use App\Http\Controllers\Controller;
use App\Settings\Support\StudioSettingsResolver;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
    public function create(): Response
    {
        $this->authorize('create', DocumentTemplate::class);

        return Inertia::render('DocumentTemplates/Create', [
            'types' => $this->typeOptions(),
            'shootKinds' => $this->shootKindOptions(),
            // Keyed by type: the create screen's type select changes the palette
            // without a round trip, and there are three small lists in total.
            'palettes' => collect(DocumentTemplateType::cases())
                ->mapWithKeys(fn (DocumentTemplateType $type) => [$type->value => DocumentBlockCatalog::paletteFor($type)])
                ->all(),
            'mergeFields' => $this->mergeFieldOptions(),
            // T-16: keyed by type for the same reason the palettes above are —
            // the type select changes what is offered without a round trip.
            // Blocks travel with the entry, so choosing one is a local edit to
            // an unsaved draft rather than a request for a document that does
            // not exist yet.
            // C-16 (#252): the projects the preview can reckon with. Newest
            // first — a template is written for the work in front of you.
            'projects' => Project::query()->latest()->limit(50)->get()
                ->map(fn (Project $project) => ['id' => $project->public_id, 'name' => $project->name])->all(),
            'starters' => StarterTemplateCatalog::byType(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', DocumentTemplate::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:100000', function (string $attribute, mixed $value, \Closure $fail) {
                if (SafeHtml::hasUnsafeHref((string) $value)) {
                    $fail('Links dürfen nur auf http, https oder mailto zeigen.');
                }
            }],
            'type' => ['required', Rule::enum(DocumentTemplateType::class)],
            'shoot_kind' => ['nullable', Rule::enum(ShootKind::class)],
            'blocks' => ['nullable', 'array'],
            'blocks.blocks' => ['nullable', 'array'],
            'blocks.blocks.*.type' => ['required_with:blocks.blocks', 'string'],
            // Laravel's validated() rebuilds its output strictly from declared
            // rule paths — without this, every block's own content is silently
            // dropped and only its type reaches storage.
            'blocks.blocks.*.data' => ['nullable', 'array'],
        ]);

        $this->assertBlocksAllowed($request, DocumentTemplateType::from($validated['type']));

        if (isset($validated['blocks']) && is_array($validated['blocks'])) {
            $validated['blocks'] = InlineHtmlSanitizer::blocks($validated['blocks']);
        }

        $template = DocumentTemplate::createFirstVersion($validated);

        AuditLogger::record(
            event: 'document_template.created',
            actor: auth('staff')->user(),
            context: ['name' => $template->name, 'type' => $template->type->value],
            subject: $template->public_id,
        );

        return redirect('/dokumentvorlagen');
    }

    /**
     * T-18: seed a chosen set of starters. Lives here rather than on
     * StudioOnboardingController because that controller is a shell — it shows
     * the wizard and stamps completion, and duplicating persistence into it is
     * what its own docblock rules out. The wizard step posts here like every
     * other step posts to the settings endpoint it belongs to.
     */
    public function seedStarters(Request $request, SeedStarterTemplates $seed): RedirectResponse
    {
        $this->authorize('create', DocumentTemplate::class);

        $validated = $request->validate([
            'keys' => ['present', 'array'],
            'keys.*' => ['string', 'max:64'],
        ]);

        // Skipping is a first-class outcome: an empty set writes nothing and
        // is not an error.
        $seed($validated['keys'], auth('staff')->user());

        return back();
    }

    public function edit(DocumentTemplate $documentTemplate): Response
    {
        $this->authorize('update', $documentTemplate);

        // A superseded version is history, not a draft — reached only by a
        // stale link, since the index only ever lists is_current rows.
        abort_unless($documentTemplate->is_current, 404);

        return Inertia::render('DocumentTemplates/Edit', [
            'template' => DocumentTemplateData::fromModel($documentTemplate),
            // One palette, not three: a family's type never changes.
            // C-16 (#252): the projects the preview can reckon with. Newest
            // first — a template is written for the work in front of you.
            'projects' => Project::query()->latest()->limit(50)->get()
                ->map(fn (Project $project) => ['id' => $project->public_id, 'name' => $project->name])->all(),
            'palette' => DocumentBlockCatalog::paletteFor($documentTemplate->type),
            'mergeFields' => $this->mergeFieldOptions(),
        ]);
    }

    public function update(Request $request, DocumentTemplate $documentTemplate): RedirectResponse
    {
        $this->authorize('update', $documentTemplate);

        abort_unless($documentTemplate->is_current, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:100000', function (string $attribute, mixed $value, \Closure $fail) {
                if (SafeHtml::hasUnsafeHref((string) $value)) {
                    $fail('Links dürfen nur auf http, https oder mailto zeigen.');
                }
            }],
            'blocks' => ['nullable', 'array'],
            'blocks.blocks' => ['nullable', 'array'],
            'blocks.blocks.*.type' => ['required_with:blocks.blocks', 'string'],
            // See the matching rule in store(): without it, validated() drops
            // every block's content and keeps only its type.
            'blocks.blocks.*.data' => ['nullable', 'array'],
        ]);

        $this->assertBlocksAllowed($request, $documentTemplate->type);

        if (isset($validated['blocks']) && is_array($validated['blocks'])) {
            $validated['blocks'] = InlineHtmlSanitizer::blocks($validated['blocks']);
        }

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
            // C-16 (#252): reckoned against a chosen project rather than
            // sample values — only then does an empty placeholder show up
            // here rather than in front of the client.
            'project' => ['nullable', 'string', 'size:24'],
        ]);

        $type = DocumentTemplateType::from($validated['type']);
        $project = ($validated['project'] ?? null) === null
            ? null
            : Project::query()->where('public_id', $validated['project'])->first();

        $resolved = MergeFieldResolver::resolve(
            $validated['body'],
            $project === null
                ? MergeFieldVocabulary::sample()
                // Nested, like MergeFieldVocabulary::sample() — the resolver
                // walks the path, it does not look up a dotted key.
                : app(DocumentMergeDataBuilder::class)->fromProject($project, $settings),
        );
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

    /**
     * A block the type may not use is a validation error, never a silent drop:
     * quietly discarding a block would hand a client a document missing wording
     * its author believed they had written.
     */
    private function assertBlocksAllowed(Request $request, DocumentTemplateType $type): void
    {
        $blocks = $request->input('blocks.blocks', []);

        foreach ($blocks as $block) {
            $blockType = $block['type'] ?? '';

            if (! DocumentBlockCatalog::allows($type, $blockType)) {
                throw ValidationException::withMessages([
                    'blocks' => "Baustein \"{$blockType}\" ist für diesen Dokumenttyp nicht erlaubt.",
                ]);
            }
        }
    }

    /** @return list<array{path: string, sample: string}> */
    private function mergeFieldOptions(): array
    {
        return collect(MergeFieldVocabulary::samples())
            ->map(fn (string $sample, string $path) => ['path' => $path, 'sample' => $sample])
            ->values()
            ->all();
    }
}
