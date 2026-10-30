<?php

namespace App\Quotes\Http\Controllers;

use App\Catalog\Actions\SavePackage;
use App\Catalog\Models\CatalogItem;
use App\Catalog\Models\Package;
use App\Crm\Actions\BookCase;
use App\Crm\Data\CaseSuggestionData;
use App\Crm\Models\ClientCase;
use App\Documents\Data\DocumentViewData;
use App\Documents\Support\InlineHtmlSanitizer;
use App\Enums\Capability;
use App\Http\Controllers\Controller;
use App\Models\StudioSettings;
use App\Notifications\Support\MailPreview;
use App\Quotes\Actions\AcceptQuote;
use App\Quotes\Actions\AdoptQuoteRecipient;
use App\Quotes\Actions\CreatePackageFromQuote;
use App\Quotes\Actions\CreateQuote;
use App\Quotes\Actions\DeleteQuote;
use App\Quotes\Actions\DuplicateQuote;
use App\Quotes\Actions\MarkQuoteSent;
use App\Quotes\Actions\RenderQuotePdf;
use App\Quotes\Actions\SendQuote;
use App\Quotes\Actions\UpdateQuote;
use App\Quotes\Data\QuoteData;
use App\Quotes\Data\QuoteRowData;
use App\Quotes\Enums\PricingMode;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Quotes\Models\QuoteItem;
use App\Quotes\Models\QuotePaymentStage;
use App\Quotes\Notifications\QuoteSent;
use App\Quotes\Screens\ReadQuoteWorkspace;
use App\Settings\Data\StudioSettingsData;
use App\Settings\Support\StudioReadiness;
use App\Support\AuditLogger;
use Carbon\CarbonInterval;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
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
        abort_unless($viewer->can(Capability::ManageQuotes->value), 403);

        $quotes = Quote::query()
            ->whereHas('case', fn ($query) => $query->visibleTo($viewer))
            ->with(['case.contacts'])
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
        $this->authorize('view', $quote->case);

        $quote->loadMissing(['items', 'predecessor', 'paymentStages', 'case', 'recipientContact']);

        return Inertia::render('Quotes/Show', [
            'projectId' => $quote->case->public_id,
            'projectName' => $quote->case->name,
            'projectIsBooked' => $quote->case->isBooked(),
            'canManageQuotes' => $viewer->can('manageQuotes', $quote->case),
            // The payment plan is the project's, not this one quote's — it
            // stays on the Angebote tab, not in this quote's own capsule.
            'canManagePaymentPlan' => false,
            'paymentPlan' => null,
            ...Arr::only($workspace($quote->case, $viewer), ['packages', 'catalogItems', 'contacts', 'canManageCatalog', 'vatSettings']),
            'quotes' => [QuoteData::fromModel($quote)],
            // QuoteData carries the recipient only as a contact id (the
            // editor's own preselect value) — this screen has no editor
            // beside it to resolve that id into a name, so it needs the
            // name itself.
            'recipientName' => $quote->recipientContact?->name,
            'views' => $quote->views()->latest('viewed_at')->get()->map(DocumentViewData::fromModel(...)),
        ]);
    }

    /**
     * The compose screen QuoteActions.vue's "Per E-Mail senden" opens into
     * instead of firing the send off blind — subject and message here ride
     * along to send() as plain request input, never stored on the quote
     * itself (Q-06 already freezes everything that is).
     */
    public function compose(Request $request, Quote $quote): InertiaResponse
    {
        $this->authorize('manageQuotes', $quote->case);
        abort_unless($quote->status === QuoteStatus::Draft, 404);

        $quote->loadMissing(['items', 'case']);
        $contact = $quote->case->primaryContact();

        return Inertia::render('Quotes/Send', [
            'projectId' => $quote->case->public_id,
            'quote' => QuoteData::fromModel($quote),
            'recipientName' => $contact?->name ?? '',
            'recipientEmail' => $contact?->email ?? '',
            'address' => StudioSettings::current()->clientAddress($quote->case->contacts()->count()),
            'mailHtml' => MailPreview::html(new QuoteSent($quote, null, MailPreview::MESSAGE), $contact ?? (object) []),
        ]);
    }

    /** The Übersicht's wizard: the project or inquiry first, then the workspace its Angebote tab shows. */
    public function create(Request $request, ReadQuoteWorkspace $workspace): InertiaResponse
    {
        $viewer = $request->user('staff');
        abort_unless($viewer->can(Capability::ManageQuotes->value), 403);

        $case = $request->filled('projekt')
            ? ClientCase::query()->visibleTo($viewer)->where('public_id', $request->query('projekt'))->first()
            : null;

        return Inertia::render('Quotes/Create', [
            'project' => $case ? CaseSuggestionData::fromModel($case) : null,
            'studioSettings' => StudioSettingsData::current(),
            // Mit dem ersten Angebot geht ein Portal-Link hinaus; was das
            // Impressum trägt, muss vorher stehen.
            'missingBusinessData' => app(StudioReadiness::class)->missingForQuote(),
            ...($case
                ? Arr::except($workspace($case, $viewer), 'paymentPlan')
                : [
                    'quotes' => [], 'packages' => [], 'catalogItems' => [], 'contacts' => [], 'canManageCatalog' => false,
                    'vatSettings' => ['mode' => StudioSettings::current()->vat_mode, 'rate' => StudioSettings::current()->effectiveVatRate(), 'note' => StudioSettings::current()->vat_note],
                ]),
        ]);
    }

    public function store(Request $request, ClientCase $case, CreateQuote $create): RedirectResponse
    {
        $this->authorize('manageQuotes', $case);

        $validated = $request->validate([
            'valid_until' => ['nullable', 'date'],
            // Scoped to active packages here rather than left to a 404 from
            // firstOrFail(): a deactivated package dropping out of what a new
            // quote may start from (Package::scopeActive()) is a validation
            // fact about this form's input, not a missing-record error.
            'package' => ['nullable', 'string', Rule::exists('packages', 'public_id')->where('is_active', true)],
            'addendum_to' => ['nullable', 'string'],
        ]);

        // Der Nachtrag lässt seinen Vorgänger angenommen, anders als die
        // Revision, die ihn auf superseded setzt (ReviseQuote).
        $predecessor = ! empty($validated['addendum_to'])
            ? $case->quotes()->where('public_id', $validated['addendum_to'])
                ->where('status', QuoteStatus::Accepted)->firstOrFail()
            : null;

        $create(
            $case,
            actor: auth('staff')->user(),
            validUntil: $validated['valid_until'] ?? null,
            package: ! empty($validated['package']) ? Package::findActive($validated['package']) : null,
            predecessor: $predecessor,
        );

        return back();
    }

    public function update(Request $request, Quote $quote, UpdateQuote $save): RedirectResponse
    {
        $this->authorize('manageQuotes', $quote->case);

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
            projectId: $quote->case_id,
        );

        return back();
    }

    /**
     * Q-10: die Schaufensterbilder dieses Angebots — das Raster hinter dem
     * Regler. Vom Paket vorbelegt (SeedQuoteFromPackage), hier für dieses
     * eine Angebot überschrieben.
     */
    public function storeShowcase(Request $request, Quote $quote): RedirectResponse
    {
        $this->authorize('update', $quote->case);

        $request->validate(['image' => ['required', 'file', 'mimes:png,jpg,jpeg', 'max:4096']]);

        $quote->addShowcaseImage($request->file('image'));

        return back();
    }

    public function destroyShowcase(Quote $quote, int $index): RedirectResponse
    {
        $this->authorize('update', $quote->case);

        $quote->removeShowcaseImage($index);

        return back();
    }

    /** Indiziert statt benannt, dieselbe Regel wie itemImage() darunter. */
    public function showcase(Quote $quote, int $index): StreamedResponse
    {
        $this->authorize('view', $quote->case);

        $path = $quote->showcasePaths()[$index] ?? null;

        abort_if($path === null, 404);

        return Storage::disk('local')->response($path);
    }

    /**
     * Q-14: das kopierte Bild einer Baustein-Zeile, für die Anzeige im
     * Editor. Über die Zeile aufgelöst und nicht über den Pfad — dieselbe
     * Regel, die update() oben trägt.
     */
    public function itemImage(Quote $quote, QuoteItem $quoteItem): StreamedResponse
    {
        $this->authorize('view', $quote->case);

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
        $this->authorize('manageQuotes', $quote->case);
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
        $this->authorize('manageQuotes', $quote->case);
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
    public function send(Request $request, Quote $quote, SendQuote $send): RedirectResponse
    {
        $this->authorize('manageQuotes', $quote->case);

        // Both optional: a caller posting straight here without going
        // through compose() (tests included) sends neither, and SendQuote
        // falls back to QuoteSent's own defaults exactly as before.
        $validated = $request->validate([
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string'],
        ]);

        $send($quote, auth('staff')->user(), null, $validated['subject'] ?? null, $validated['message'] ?? null);

        return redirect("/auftraege/{$quote->case->public_id}");
    }

    /**
     * T-11: delivered by the studio itself (WhatsApp, their own mail
     * client) — same MarkQuoteSent state change as send(), channel 'self',
     * no notification.
     */
    public function markSent(Quote $quote, MarkQuoteSent $mark): RedirectResponse
    {
        $this->authorize('manageQuotes', $quote->case);

        $mark($quote, 'self', auth('staff')->user());

        return back();
    }

    public function markAccepted(Quote $quote, AcceptQuote $accept, BookCase $book): RedirectResponse
    {
        $this->authorize('manageQuotes', $quote->case);

        $contact = $quote->case->primaryContact()
            ?? throw ValidationException::withMessages(['quote' => __('quotes.noPrimaryContact')]);

        $accept($quote, $contact, null);

        // Every role manages quotes, but booking is Admin-only (StaffRole):
        // the yes is recorded either way, the booking only when this user may book.
        if (Gate::allows('book', $quote->case)) {
            $book($quote->case, auth('staff')->user());
        }

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
        $this->authorize('manageQuotes', $quote->case);

        $copy = $duplicate($quote, auth('staff')->user());

        return redirect("/angebote/{$copy->public_id}");
    }

    /**
     * Only a draft — Quote::booted() already refuses to write a sent one
     * further, and deleting one that a client has already seen would erase
     * a record they might still hold a copy or a mail of.
     */
    public function destroy(Quote $quote, DeleteQuote $delete): RedirectResponse
    {
        $this->authorize('manageQuotes', $quote->case);

        $projectId = $quote->case->public_id;

        try {
            $delete($quote, auth('staff')->user());
        } catch (DomainException) {
            abort(422, 'Nur ein Entwurf lässt sich löschen.');
        }

        return redirect("/auftraege/{$projectId}/angebote");
    }

    /**
     * Schreibt die Anschrift des Angebots in den Kontakt zurück, ohne
     * Request-Body: eine ausgefüllte, aber noch nicht gespeicherte
     * Formularzeile darf den Kontakt nie überschreiben.
     */
    public function adoptRecipient(Quote $quote, AdoptQuoteRecipient $adopt): RedirectResponse
    {
        $this->authorize('manageQuotes', $quote->case);

        abort_if($quote->recipient_contact_id === null, 404);

        $adopt($quote);

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
        $this->authorize('manageQuotes', $quote->case);

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
        $this->authorize('view', $quote->case);

        $quote->loadMissing(['items', 'paymentStages', 'recipientContact', 'case']);

        return response($render->html($quote), 200, ['Content-Type' => 'text/html']);
    }

    /**
     * Same shape update() validates, on an unsaved copy — every field
     * optional, since the editor posts here on each debounced keystroke of
     * a half-filled draft. Nothing here is written: not to $quote, not to
     * its items or stages, so the live sheet always trails the form and
     * never the stored row.
     */
    public function preview(Request $request, Quote $quote, RenderQuotePdf $render): Response
    {
        $this->authorize('manageQuotes', $quote->case);

        $validated = $request->validate([
            'pricing_mode' => ['nullable', Rule::enum(PricingMode::class)],
            'recipient_street' => ['nullable', 'string', 'max:255'],
            'recipient_postcode' => ['nullable', 'string', 'max:32'],
            'recipient_city' => ['nullable', 'string', 'max:255'],
            'intro' => ['nullable', 'string', 'max:20000'],
            'outro' => ['nullable', 'string', 'max:20000'],
            'discount_type' => ['nullable', 'in:percentage,amount'],
            'discount_value' => ['nullable', 'integer', 'min:1'],
            'discount_hidden' => ['sometimes', 'boolean'],
            'items' => ['array'],
            'items.*.title' => ['nullable', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:2000'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'integer', 'min:0'],
            'items.*.discount_type' => ['nullable', 'in:percentage,amount'],
            'items.*.discount_value' => ['nullable', 'integer', 'min:1'],
            'items.*.vat_rate' => ['nullable', 'integer', 'min:1', 'max:10000'],
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

        $preview = new Quote(['case_id' => $quote->case_id]);
        $preview->setRelation('case', $quote->case);
        // Der Name steht am Kontakt, nicht am Formular; die Anschrift kommt
        // aus dem Entwurf, damit das Blatt dem Feld folgt.
        $preview->setRelation('recipientContact', $quote->recipientContact);
        $preview->fill([
            'pricing_mode' => $validated['pricing_mode'] ?? PricingMode::Classic->value,
            'recipient_street' => $validated['recipient_street'] ?? null,
            'recipient_postcode' => $validated['recipient_postcode'] ?? null,
            'recipient_city' => $validated['recipient_city'] ?? null,
            'intro' => InlineHtmlSanitizer::letter($validated['intro'] ?? null),
            'outro' => InlineHtmlSanitizer::letter($validated['outro'] ?? null),
            'discount_type' => $validated['discount_type'] ?? null,
            'discount_value' => $validated['discount_value'] ?? null,
            'discount_hidden' => $validated['discount_hidden'] ?? false,
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
                'vat_rate' => $item['vat_rate'] ?? null,
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
        $preview->fill(Quote::computeVat($preview->net, $items));

        return response($render->html($preview), 200, ['Content-Type' => 'text/html']);
    }
}
