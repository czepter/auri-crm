<?php

namespace App\Quotes\Http\Controllers;

use App\Catalog\Actions\CreatePackageFromQuote;
use App\Catalog\Actions\SavePackage;
use App\Catalog\Models\CatalogItem;
use App\Catalog\Models\Package;
use App\Crm\Data\ProjectSuggestionData;
use App\Crm\Models\Project;
use App\Enums\Capability;
use App\Http\Controllers\Controller;
use App\Numbering\Actions\AllocateDocumentNumber;
use App\Numbering\Enums\DocumentType;
use App\Quotes\Actions\DuplicateQuote;
use App\Quotes\Actions\MarkQuoteSent;
use App\Quotes\Actions\ReadQuoteWorkspace;
use App\Quotes\Actions\RenderQuotePdf;
use App\Quotes\Actions\ResolveQuoteRecipient;
use App\Quotes\Actions\SeedQuoteFromPackage;
use App\Quotes\Actions\SendQuote;
use App\Quotes\Actions\UpdateQuote;
use App\Quotes\Data\QuoteData;
use App\Quotes\Data\QuoteRowData;
use App\Quotes\Enums\PricingMode;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Quotes\Models\QuoteItem;
use App\Quotes\Models\QuotePaymentStage;
use App\Settings\Support\StudioSettingsResolver;
use App\Support\AuditLogger;
use Carbon\CarbonInterval;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuoteController extends Controller
{
    /** Every quote across every project, newest first — the sidebar's own Angebote entry. */
    public function index(Request $request): InertiaResponse
    {
        $viewer = $request->user('staff');
        abort_unless($viewer->role->allows(Capability::ManageQuotes), 403);

        $quotes = Quote::query()
            ->whereHas('project', fn ($query) => $query->visibleTo($viewer))
            ->with(['project.contacts'])
            ->latest()
            ->get();

        return Inertia::render('Quotes/Index', [
            'quotes' => QuoteRowData::collect($quotes->map(QuoteRowData::fromModel(...))),
        ]);
    }

    /**
     * One quote on its own screen, reached from the overview — the same
     * QuoteWorkspace the project's Angebote tab renders, scoped to just this
     * quote rather than the project's whole list and its "new quote" tool.
     */
    public function show(Request $request, Quote $quote, ReadQuoteWorkspace $workspace): InertiaResponse
    {
        $viewer = $request->user('staff');
        $this->authorize('view', $quote->project);

        $quote->loadMissing(['items', 'predecessor', 'paymentStages', 'project', 'recipientContact']);

        return Inertia::render('Quotes/Show', [
            'projectId' => $quote->project->public_id,
            'projectName' => $quote->project->name,
            'projectIsBooked' => $quote->project->job !== null,
            'canManageQuotes' => $viewer->can('manageQuotes', $quote->project),
            // The payment plan is the project's, not this one quote's — it
            // stays on the Angebote tab, not in this quote's own capsule.
            'canManagePaymentPlan' => false,
            'paymentPlan' => null,
            ...Arr::only($workspace($quote->project, $viewer), ['packages', 'catalogItems', 'contacts', 'canManageCatalog', 'vatSettings']),
            'quotes' => [QuoteData::fromModel($quote)],
            // QuoteData carries the recipient only as a contact id (the
            // editor's own preselect value) — this screen has no editor
            // beside it to resolve that id into a name, so it needs the
            // name itself.
            'recipientName' => $quote->recipientContact?->name,
        ]);
    }

    /** The Übersicht's wizard: the project or inquiry first, then the workspace its Angebote tab shows. */
    public function create(Request $request, ReadQuoteWorkspace $workspace, StudioSettingsResolver $settings): InertiaResponse
    {
        $viewer = $request->user('staff');
        abort_unless($viewer->role->allows(Capability::ManageQuotes), 403);

        $project = $request->filled('projekt')
            ? Project::query()->visibleTo($viewer)->where('public_id', $request->query('projekt'))->first()
            : null;

        return Inertia::render('Quotes/Create', [
            'project' => $project ? ProjectSuggestionData::fromModel($project) : null,
            ...($project
                ? Arr::except($workspace($project, $viewer), 'paymentPlan')
                : [
                    'quotes' => [], 'packages' => [], 'catalogItems' => [], 'contacts' => [], 'canManageCatalog' => false,
                    'vatSettings' => ['mode' => $settings->vatMode(), 'rate' => $settings->effectiveVatRate(), 'note' => $settings->vatNote()],
                ]),
        ]);
    }

    public function store(Request $request, Project $project, SeedQuoteFromPackage $seed, StudioSettingsResolver $settings, AllocateDocumentNumber $allocate, ResolveQuoteRecipient $resolveRecipient): RedirectResponse
    {
        $this->authorize('manageQuotes', $project);

        $validated = $request->validate([
            'valid_until' => ['nullable', 'date'],
            // Scoped to active packages here rather than left to a 404 from
            // firstOrFail(): a deactivated package dropping out of what a new
            // quote may start from (Package::scopeActive()) is a validation
            // fact about this form's input, not a missing-record error.
            'package' => ['nullable', 'string', Rule::exists('packages', 'public_id')->where('is_active', true)],
            'addendum_to' => ['nullable', 'string'],
        ]);

        // T-05's fallback: Package carries no default_validity_days column
        // today (App\Catalog\Models\Package), so a quote with no explicit
        // valid_until — seeded from a package or not — takes it from the
        // tenant's own default. Read once, at creation, and never again: a
        // later change to the setting is not this quote's concern (see
        // StudioSettingsResolver::quoteValidityDays()).
        $validUntil = $validated['valid_until'] ?? now()->addDays($settings->quoteValidityDays())->toDateString();

        // Der Nachtrag lässt seinen Vorgänger angenommen, anders als die
        // Revision, die ihn auf superseded setzt (ReviseQuote).
        $predecessor = ! empty($validated['addendum_to'])
            ? $project->quotes()->where('public_id', $validated['addendum_to'])
                ->where('status', QuoteStatus::Accepted)->firstOrFail()
            : null;

        $quote = DB::transaction(function () use ($project, $validUntil, $predecessor, $allocate, $resolveRecipient) {
            $allocation = ($allocate)(documentType: DocumentType::Quote);
            $recipient = ($resolveRecipient)($project);

            return $project->quotes()->create([
                'valid_until' => $validUntil,
                'is_addendum' => $predecessor !== null,
                'predecessor_id' => $predecessor?->id,
                'number' => $allocation['number'],
                'recipient_contact_id' => $recipient?->id,
                'recipient_street' => $recipient?->street,
                'recipient_postcode' => $recipient?->postcode,
                'recipient_city' => $recipient?->city,
            ]);
        });

        if (! empty($validated['package'])) {
            $package = Package::active()->where('public_id', $validated['package'])->firstOrFail();
            $seed($quote, $package);
        }

        AuditLogger::record(
            event: 'quote.created',
            actor: auth('staff')->user(),
            context: ['project' => $project->public_id],
            subject: $quote->public_id,
            projectId: $project->id,
        );

        return back();
    }

    public function update(Request $request, Quote $quote, UpdateQuote $save): RedirectResponse
    {
        $this->authorize('manageQuotes', $quote->project);

        $validated = $request->validate([...UpdateQuote::rules(), 'items.*.remember' => ['boolean']]);

        // Der Schlüssel kommt nur mit, wenn der Bildschirm das Häkchen
        // gerendert hat. Kommt er trotzdem, ist das kein Bedienschritt, den
        // man still verwerfen darf.
        if (collect($validated['items'] ?? [])->contains(fn (array $item) => array_key_exists('remember', $item))
            && Gate::denies('create', Package::class)) {
            abort(403);
        }

        $save($quote, $validated);

        $this->syncCatalog($validated['items'] ?? []);

        AuditLogger::recordThrottled(
            event: 'quote.updated',
            within: CarbonInterval::minutes(15),
            actor: auth('staff')->user(),
            subject: $quote->public_id,
            projectId: $quote->project_id,
        );

        return back();
    }

    /**
     * Q-14: das kopierte Bild einer Baustein-Zeile, für die Anzeige im
     * Editor. Über die Zeile aufgelöst und nicht über den Pfad — dieselbe
     * Regel, die update() oben trägt.
     */
    public function itemImage(Quote $quote, QuoteItem $quoteItem): StreamedResponse
    {
        $this->authorize('view', $quote->project);

        abort_unless($quoteItem->quote_id === $quote->id && $quoteItem->image_path !== null, 404);

        return Storage::disk('local')->response($quoteItem->image_path);
    }

    /**
     * Zwei Prüfungen, weil zwei Dinge berührt werden. Heute hält jede Rolle
     * beides — getrennt geprüft wird aus dem Grund, den PackagePolicy nennt:
     * P6/I-01 gibt Token gegen einzelne Fähigkeiten aus.
     *
     * Bei jedem Status verfügbar, nicht nur beim Entwurf: gelesen wird nur,
     * und das brauchbarste Paket ist das angenommene Angebot.
     */
    public function saveAsPackage(Request $request, Quote $quote, CreatePackageFromQuote $create): RedirectResponse
    {
        $this->authorize('manageQuotes', $quote->project);
        $this->authorize('create', Package::class);

        $validated = $request->validate(['name' => ['required', 'string', 'max:255']]);

        // Am Namen, weil das Formular kein zweites Feld hat, an dem eine
        // Meldung stehen könnte.
        if ($quote->items->isEmpty()) {
            throw ValidationException::withMessages(['name' => __('quotes.emptyPackage')]);
        }

        $package = $create($quote, $validated['name']);

        AuditLogger::record(
            event: 'package.created',
            actor: auth('staff')->user(),
            context: ['name' => $package->name, 'source' => 'quote'],
            subject: $package->public_id,
        );

        return back();
    }

    /**
     * Die Zeile kommt aus dem Entwurf statt per id: eine gerade eingefügte
     * Position hat bis zum nächsten Laden noch keine.
     */
    public function saveItemAsPackage(Request $request, Quote $quote, SavePackage $save): RedirectResponse
    {
        $this->authorize('manageQuotes', $quote->project);
        $this->authorize('create', Package::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'item' => ['required', 'array'],
            ...collect(SavePackage::rules())
                ->filter(fn ($rule, string $key) => str_starts_with($key, 'items.*.') && $key !== 'items.*.id')
                ->mapWithKeys(fn ($rule, string $key) => [str_replace('items.*.', 'item.', $key) => $rule])
                ->all(),
        ]);

        $package = $save(new Package, ['name' => $validated['name'], 'items' => [$validated['item']]]);

        AuditLogger::record(
            event: 'package.created',
            actor: auth('staff')->user(),
            context: ['name' => $package->name, 'source' => 'quote_item'],
            subject: $package->public_id,
        );

        return back();
    }

    /**
     * Q-06's state change: allocates the number, snapshots the VAT and the
     * already-computed amounts, and sends the client mail — all of
     * SendQuote::__invoke(), audit logging included, so nothing repeats here.
     */
    public function send(Quote $quote, SendQuote $send): RedirectResponse
    {
        $this->authorize('manageQuotes', $quote->project);

        $send($quote, auth('staff')->user());

        return back();
    }

    /**
     * T-11: delivered by the studio itself (WhatsApp, their own mail
     * client) — same MarkQuoteSent state change as send(), channel 'self',
     * no notification.
     */
    public function markSent(Quote $quote, MarkQuoteSent $mark): RedirectResponse
    {
        $this->authorize('manageQuotes', $quote->project);

        $mark($quote, 'self', auth('staff')->user());

        return back();
    }

    /**
     * QuoteActions.vue's split button remembers itself here: whichever of
     * download/copy_link/send_by_mail was reached for last becomes the
     * default next time, on this user rather than a cookie — the same
     * reasoning as LeadBoardController::chooseView().
     */
    public function chooseLastAction(Request $request): RedirectResponse
    {
        $validated = $request->validate(['action' => ['required', 'in:download,copy_link,send_by_mail']]);

        auth('staff')->user()->update(['last_quote_action' => $validated['action']]);

        return back();
    }

    /**
     * A new draft on the same project, carrying this quote's positions and
     * terms — never its history (status, send, vat snapshot, predecessor).
     * Redirects to the copy itself: duplicating is the first step of
     * editing it, not a fire-and-forget like markSent.
     */
    public function duplicate(Quote $quote, DuplicateQuote $duplicate): RedirectResponse
    {
        $this->authorize('manageQuotes', $quote->project);

        $copy = $duplicate($quote);

        AuditLogger::record(
            event: 'quote.duplicated',
            actor: auth('staff')->user(),
            context: ['from' => $quote->public_id],
            subject: $copy->public_id,
            projectId: $quote->project_id,
        );

        return redirect("/angebote/{$copy->public_id}");
    }

    /**
     * Only a draft — Quote::booted() already refuses to write a sent one
     * further, and deleting one that a client has already seen would erase
     * a record they might still hold a copy or a mail of.
     */
    public function destroy(Quote $quote): RedirectResponse
    {
        $this->authorize('manageQuotes', $quote->project);
        abort_unless($quote->status === QuoteStatus::Draft, 422, 'Nur ein Entwurf lässt sich löschen.');

        $projectId = $quote->project->public_id;

        AuditLogger::record(
            event: 'quote.deleted',
            actor: auth('staff')->user(),
            context: ['number' => $quote->number],
            subject: $quote->public_id,
            projectId: $quote->project_id,
        );

        $quote->delete();

        return redirect("/auftraege/{$projectId}/angebote");
    }

    /**
     * Schreibt die Anschrift des Angebots in den Kontakt zurück, ohne
     * Request-Body: eine ausgefüllte, aber noch nicht gespeicherte
     * Formularzeile darf den Kontakt nie überschreiben.
     */
    public function adoptRecipient(Quote $quote): RedirectResponse
    {
        $this->authorize('manageQuotes', $quote->project);

        abort_if($quote->recipient_contact_id === null, 404);

        $quote->recipientContact->update([
            'street' => $quote->recipient_street,
            'postcode' => $quote->recipient_postcode,
            'city' => $quote->recipient_city,
        ]);

        return back();
    }

    /**
     * Das Häkchen ist keine Spalte auf quote_items, sondern die Frage "gibt es
     * einen Katalogeintrag mit diesem Titel". Eine Zeile ohne den Schlüssel
     * stellt die Frage nicht und lässt die Bibliothek unberührt.
     *
     * @param  list<array<string, mixed>>  $items
     */
    private function syncCatalog(array $items): void
    {
        foreach ($items as $item) {
            if (! array_key_exists('remember', $item)) {
                continue;
            }

            if ($item['remember']) {
                CatalogItem::updateOrCreate(
                    ['title' => $item['title']],
                    ['description' => $item['description'] ?? null, 'unit_price' => $item['unit_price'] ?? null],
                );

                continue;
            }

            CatalogItem::where('title', $item['title'])->delete();
        }
    }

    public function download(Quote $quote, RenderQuotePdf $render): Response
    {
        $this->authorize('manageQuotes', $quote->project);

        return response($render($quote), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.($quote->number ?? $quote->public_id).'.pdf"',
        ]);
    }

    /**
     * The print sheet of a quote as it stands saved — QuotePreviewPane's read
     * path, for a quote no draft is re-posting on every keystroke (sent,
     * accepted, superseded). `view` rather than `manageQuotes`: seeing the
     * sheet a client already has needs no write capability.
     */
    public function previewSheet(Quote $quote, RenderQuotePdf $render): Response
    {
        $this->authorize('view', $quote->project);

        $quote->loadMissing(['items', 'paymentStages', 'recipientContact', 'project']);

        return response($render->html($quote), 200, ['Content-Type' => 'text/html']);
    }

    /**
     * Same shape update() validates, on an unsaved copy — every field
     * optional, since the editor posts here on each debounced keystroke of
     * a half-filled draft. Nothing here is written: not to $quote, not to
     * its items or stages, so the live sheet always trails the form and
     * never the stored row.
     */
    public function preview(Request $request, Quote $quote, RenderQuotePdf $render, StudioSettingsResolver $settings): Response
    {
        $this->authorize('manageQuotes', $quote->project);

        $validated = $request->validate([
            'pricing_mode' => ['nullable', Rule::enum(PricingMode::class)],
            'recipient_street' => ['nullable', 'string', 'max:255'],
            'recipient_postcode' => ['nullable', 'string', 'max:32'],
            'recipient_city' => ['nullable', 'string', 'max:255'],
            'intro' => ['nullable', 'string', 'max:5000'],
            'outro' => ['nullable', 'string', 'max:5000'],
            'discount_type' => ['nullable', 'in:percentage,amount'],
            'discount_value' => ['nullable', 'integer', 'min:1'],
            'items' => ['array'],
            'items.*.title' => ['nullable', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:2000'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'integer', 'min:0'],
            'items.*.discount_type' => ['nullable', 'in:percentage,amount'],
            'items.*.discount_value' => ['nullable', 'integer', 'min:1'],
            'items.*.selectable' => ['sometimes', 'boolean'],
            'items.*.selected' => ['sometimes', 'boolean'],
            'items.*.outcome' => ['nullable', 'string', 'max:255'],
            'hourly_rate' => ['nullable', 'integer', 'min:1'],
            'min_hours' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'max_hours' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'images_per_hour' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'hours' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'time_title' => ['nullable', 'string', 'max:255'],
            'time_description' => ['nullable', 'string', 'max:255'],
            'time_sort_order' => ['nullable', 'integer', 'min:0', 'max:2000'],
            'stages' => ['array'],
            'stages.*.percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'stages.*.label' => ['nullable', 'string', 'max:255'],
            'stages.*.minimum' => ['nullable', 'integer', 'min:0'],
        ]);

        $preview = new Quote(['project_id' => $quote->project_id]);
        $preview->setRelation('project', $quote->project);
        // Der Name steht am Kontakt, nicht am Formular; die Anschrift kommt
        // aus dem Entwurf, damit das Blatt dem Feld folgt.
        $preview->setRelation('recipientContact', $quote->recipientContact);
        $preview->fill([
            'pricing_mode' => $validated['pricing_mode'] ?? PricingMode::Classic->value,
            'recipient_street' => $validated['recipient_street'] ?? null,
            'recipient_postcode' => $validated['recipient_postcode'] ?? null,
            'recipient_city' => $validated['recipient_city'] ?? null,
            'intro' => $validated['intro'] ?? null,
            'outro' => $validated['outro'] ?? null,
            'discount_type' => $validated['discount_type'] ?? null,
            'discount_value' => $validated['discount_value'] ?? null,
            'hourly_rate' => $validated['hourly_rate'] ?? null,
            'min_hours' => $validated['min_hours'] ?? null,
            'max_hours' => $validated['max_hours'] ?? null,
            'images_per_hour' => $validated['images_per_hour'] ?? null,
            'hours' => $validated['hours'] ?? null,
            'time_title' => $validated['time_title'] ?? null,
            'time_description' => $validated['time_description'] ?? null,
            'time_sort_order' => $validated['time_sort_order'] ?? 0,
        ]);

        $items = collect($validated['items'] ?? [])->map(function (array $item) {
            $quoteItem = new QuoteItem([
                'title' => $item['title'] ?? '',
                'description' => $item['description'] ?? null,
                'quantity' => $item['quantity'] ?? 1,
                'unit_price' => $item['unit_price'] ?? null,
                'discount_type' => $item['discount_type'] ?? null,
                'discount_value' => $item['discount_value'] ?? null,
                'selectable' => $item['selectable'] ?? false,
                'selected' => $item['selected'] ?? false,
                'outcome' => $item['outcome'] ?? null,
            ]);
            $quoteItem->discount_amount = $quoteItem->resolveDiscountAmount();

            return $quoteItem;
        });
        $preview->setRelation('items', $items);

        $preview->setRelation('paymentStages', collect($validated['stages'] ?? [])->map(fn (array $stage) => new QuotePaymentStage([
            'percent' => $stage['percent'] ?? 0,
            'label' => $stage['label'] ?? '',
            'minimum' => $stage['minimum'] ?? 0,
        ])));

        $preview->fill(Quote::computeTotals($items, $preview->discount_type, $preview->discount_value));
        $preview->fill(Quote::computeVat($preview->net, $settings));

        return response($render->html($preview), 200, ['Content-Type' => 'text/html']);
    }
}
