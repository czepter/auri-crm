<?php

namespace App\Payments\Http\Controllers;

use App\Catalog\Data\CatalogItemData;
use App\Catalog\Data\PackageData;
use App\Catalog\Models\CatalogItem;
use App\Catalog\Models\Package;
use App\Crm\Actions\CreateContact;
use App\Crm\Data\CaseSuggestionData;
use App\Crm\Data\ContactSuggestionData;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Crm\Models\Shoot;
use App\Documents\Actions\SeedStarterTemplates;
use App\Documents\Data\MailTemplateData;
use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Support\InlineHtmlSanitizer;
use App\Documents\Support\MergeFieldVocabulary;
use App\Documents\Support\StarterTemplateCatalog;
use App\Enums\Capability;
use App\Http\Controllers\Controller;
use App\Models\StudioSettings;
use App\Notifications\NotificationService;
use App\Notifications\Support\MailPreview;
use App\Payments\Actions\CancelInvoice;
use App\Payments\Actions\IssueCreditNote;
use App\Payments\Actions\IssueInvoice;
use App\Payments\Actions\RenderInvoicePdf;
use App\Payments\Data\InvoiceDetailData;
use App\Payments\Data\InvoiceRowData;
use App\Payments\Data\PaymentPlanData;
use App\Payments\Enums\CancellationKind;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Models\CreditNote;
use App\Payments\Models\Invoice;
use App\Payments\Models\InvoiceDraft;
use App\Payments\Models\InvoiceItem;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Models\PaymentPlan;
use App\Payments\Notifications\CreditNoteSent;
use App\Payments\Notifications\InvoiceSent;
use App\Payments\Support\InvoiceDocument;
use App\Payments\Support\InvoiceFigures;
use App\Payments\Support\InvoiceMergeData;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Quotes\Models\QuoteItem;
use App\Settings\Data\StudioSettingsData;
use App\Settings\Support\StudioReadiness;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * #480: the invoice as a document of its own — listed, issued, sent, retired
 * and credited here. Every action checks the same authority the project's
 * payment plan does; an invoice without a project needs the capability alone.
 */
class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        self::authorizeProject(null);

        $viewer = $request->user('staff');

        $invoices = Invoice::query()
            ->where(fn ($query) => $query->whereNull('case_id')->orWhereHas('case', fn ($project) => $project->visibleTo($viewer)))
            ->with(['installments', 'creditNotes', 'contact', 'case', 'views'])
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->get();

        $drafts = InvoiceDraft::query()
            ->where(fn ($query) => $query->whereNull('case_id')->orWhereHas('case', fn ($project) => $project->visibleTo($viewer)))
            ->with(['case', 'contact'])
            ->latest('updated_at')
            ->get()
            ->map(fn (InvoiceDraft $draft) => [
                'id' => $draft->public_id,
                'recipient' => $draft->contact?->name ?? $draft->payload['contact']['name'] ?? null,
                'projectName' => $draft->case?->name,
                'updatedOn' => $draft->updated_at->toDateString(),
            ]);

        return Inertia::render('Invoices/Index', [
            'invoices' => InvoiceRowData::collect($invoices->map(InvoiceRowData::fromModel(...))),
            'drafts' => $drafts,
            // The export route is gated by ManageSettings, so the years appear only for roles it lets in.
            'exportYears' => $viewer->can(Capability::ManageSettings->value)
                ? Invoice::query()->pluck('issued_at')->map(fn ($at) => (int) $at->format('Y'))->unique()->sortDesc()->values()
                : [],
        ]);
    }

    public function show(Invoice $invoice): Response
    {
        self::authorizeProject($invoice->case);

        return Inertia::render('Invoices/Show', ['invoice' => InvoiceDetailData::fromModel($invoice)]);
    }

    public function create(
        Request $request,
        RenderInvoicePdf $render,
        SeedStarterTemplates $seed,
        StudioReadiness $readiness,
        ?InvoiceDraft $draft = null,
    ): Response {
        self::authorizeProject(null);

        // Die Rechnungsvorlage entsteht hier statt beim Abschluss des
        // Assistenten: erst jetzt steht fest, ob sie eine USt.-Zeile trägt.
        // SeedStarterTemplates dedupliziert über Typ und Name, ein zweiter
        // Aufruf schreibt also nichts.
        $seed(
            StarterTemplateCatalog::invoiceDefaults(StudioSettings::current()->vat_mode),
            $request->user('staff'),
            StudioSettings::current()->client_register,
        );

        $viewer = $request->user('staff');

        // The Schnellwahl: every rate still to be billed, keyed by project.
        $plans = PaymentPlan::query()
            ->whereNull('settled_at')
            ->whereHas('installments', fn ($query) => $query->whereNull('invoice_id')->whereNull('cancelled_at'))
            ->whereHas('case', fn ($query) => $query->visibleTo($viewer))
            ->with(['installments', 'case'])
            ->get();

        $rates = $plans->mapWithKeys(fn (PaymentPlan $plan) => [
            $plan->case->public_id => collect(PaymentPlanData::fromModel($plan)->installments)
                ->filter(fn ($row) => $row->invoiceNumber === null && $row->status !== 'cancelled')
                ->map(fn ($row) => ['id' => $row->id, 'label' => $row->label, 'amount' => $row->amount, 'dueOn' => $row->dueOn])
                ->values(),
        ]);

        // What the search offers before anyone types: the projects with a rate
        // still open, which is the invoice someone came here to write.
        $billable = $plans
            ->filter(fn (PaymentPlan $plan) => $rates[$plan->case->public_id]->isNotEmpty())
            ->map(fn (PaymentPlan $plan) => CaseSuggestionData::fromModel($plan->case))
            ->values();

        $projectId = $draft === null ? $request->query('projekt') : $draft->case?->public_id;
        $project = $projectId === null
            ? null
            : ClientCase::query()->visibleTo($viewer)->where('public_id', $projectId)->first();

        return Inertia::render('Invoices/Create', [
            'rates' => $rates,
            'quoteLines' => $this->unbilledQuoteLines($viewer),
            'billable' => $billable,
            'project' => $project === null ? null : CaseSuggestionData::fromModel($project),
            'packages' => PackageData::collect(Package::active()->with('items')->orderBy('name')->get()),
            'catalogItems' => CatalogItemData::collect(CatalogItem::query()->orderBy('title')->get()),
            'vatSettings' => [
                'mode' => StudioSettings::current()->vat_mode,
                'rate' => StudioSettings::current()->effectiveVatRate(),
                'note' => StudioSettings::current()->vat_note,
            ],
            // The invoice template's prose: the starting point for this
            // invoice's own letter, merge fields left for the render.
            'intro' => InlineHtmlSanitizer::letter($render->currentTemplate(DocumentTemplateType::Invoice)?->body) ?? '',
            'studioSettings' => StudioSettingsData::current(),
            // Was IssueInvoice beim Ausstellen ohnehin verlangt, steht hier
            // vor dem Formular statt hinter dem Absenden.
            'missingInvoiceData' => $readiness->missingForInvoice(),
            'firstInvoice' => ! Invoice::query()->exists(),
            'draft' => $draft === null ? null : [
                'id' => $draft->public_id,
                'payload' => $draft->payload,
                'contact' => $draft->contact === null ? null : ContactSuggestionData::fromModel($draft->contact->loadCount('cases')),
            ],
        ]);
    }

    public function showDraft(
        Request $request,
        InvoiceDraft $draft,
        RenderInvoicePdf $render,
        SeedStarterTemplates $seed,
        StudioReadiness $readiness,
    ): Response {
        return $this->create($request, $render, $seed, $readiness, $draft);
    }

    public function storeDraft(Request $request): RedirectResponse
    {
        self::authorizeProject(null);

        $draft = InvoiceDraft::create([...$this->draftAttributes($request), 'created_by' => $request->user('staff')->id]);

        return redirect("/rechnungen/entwuerfe/{$draft->public_id}");
    }

    public function updateDraft(Request $request, InvoiceDraft $draft): RedirectResponse
    {
        self::authorizeProject(null);

        $draft->update($this->draftAttributes($request));

        return back();
    }

    public function destroyDraft(InvoiceDraft $draft): RedirectResponse
    {
        self::authorizeProject(null);

        $draft->delete();

        return redirect('/rechnungen');
    }

    /** @return array{payload: array<string, mixed>, case_id: ?int, contact_id: ?int} */
    private function draftAttributes(Request $request): array
    {
        $validated = $request->validate($this->rules());

        return [
            'payload' => $validated,
            'case_id' => $this->case($validated)?->id,
            'contact_id' => isset($validated['contact_id'])
                ? Contact::query()->where('public_id', $validated['contact_id'])->firstOrFail()->id
                : null,
        ];
    }

    /**
     * The lines of each visible project's accepted quote that no standing
     * invoice carries yet, keyed by project. ponytail: every visible project,
     * not just the ones with an open rate — add a lookup when that list grows
     * past what a form should be sent.
     *
     * @return Collection<string, Collection<int, array<string, mixed>>>
     */
    private function unbilledQuoteLines($viewer): Collection
    {
        $quotes = Quote::query()
            ->where('status', QuoteStatus::Accepted)
            ->whereHas('case', fn ($project) => $project->visibleTo($viewer))
            ->with(['items', 'case'])
            ->latest('accepted_at')
            ->get()
            ->unique('case_id');

        $billed = InvoiceItem::query()
            ->whereIn('quote_item_id', $quotes->pluck('items')->flatten()->pluck('id'))
            ->whereHas('invoice', fn ($invoice) => $invoice->whereNull('cancelled_at'))
            ->pluck('quote_item_id')
            ->all();

        return $quotes
            ->mapWithKeys(fn (Quote $quote) => [
                $quote->case->public_id => $quote->items
                    ->reject(fn (QuoteItem $item) => in_array($item->id, $billed, true))
                    ->map(fn (QuoteItem $item) => [
                        'id' => $item->public_id,
                        'title' => $item->title,
                        'description' => $item->description,
                        'quantity' => (int) $item->quantity,
                        'unitPrice' => (int) $item->unit_price,
                        'vatRate' => $item->vat_rate,
                    ])
                    ->values(),
            ])
            ->filter(fn (Collection $lines) => $lines->isNotEmpty());
    }

    public function store(Request $request, IssueInvoice $issue, CreateContact $createContact): RedirectResponse
    {
        self::authorizeProject(null);

        $validated = $request->validate([
            ...$this->rules(),
            'issued_on' => [...$this->rules()['issued_on'], $this->notBeforeLatestInvoice(...)],
            'draft_id' => ['nullable', 'string'],
            'contact.name' => ['required_without_all:contact_id,case_id', 'nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.title' => ['required', 'string', 'max:255'],
        ]);

        $project = $this->case($validated);
        $contact = match (true) {
            isset($validated['contact_id']) => Contact::query()->where('public_id', $validated['contact_id'])->firstOrFail(),
            isset($validated['contact']['name']) => $createContact(array_filter($validated['contact'], fn ($value) => $value !== null)),
            default => $project?->primaryContact() ?? new Contact(['name' => __('payments.previewRecipient')]),
        };

        $invoice = $issue->positions(
            $contact,
            $project,
            $this->positions($validated, strict: true),
            $validated['due_on'] ?? null,
            [
                'issued_on' => $validated['issued_on'] ?? null,
                'intro' => InlineHtmlSanitizer::letter($validated['intro'] ?? null),
                'outro' => InlineHtmlSanitizer::letter($validated['outro'] ?? null),
                'discount_type' => $validated['discount_type'] ?? null,
                'discount_value' => $validated['discount_value'] ?? null,
            ],
            $request->user('staff'),
        );

        if (isset($validated['draft_id'])) {
            InvoiceDraft::query()->where('public_id', $validated['draft_id'])->delete();
        }

        return redirect("/rechnungen/{$invoice->public_id}/versenden");
    }

    /** Invoice numbers run on in sequence, so their dates must not run backwards. */
    private function notBeforeLatestInvoice(string $attribute, mixed $value, \Closure $fail): void
    {
        $latest = Invoice::query()->orderByDesc('issued_at')->first()?->issued_at;

        if ($latest !== null && $value !== null && Carbon::parse($value)->startOfDay()->lt($latest->startOfDay())) {
            $fail(__('payments.issuedBeforeLatest', ['date' => $latest->format('d.m.Y')]));
        }
    }

    /**
     * The sheet the create screen shows beside the form: an unsaved invoice
     * rendered through the same RenderInvoicePdf the issued one takes.
     * Every field optional, since the form posts on each debounced keystroke.
     * Nothing is written and no number drawn.
     */
    public function preview(
        Request $request,
        RenderInvoicePdf $render,
        InvoiceMergeData $mergeData,
    ): HttpResponse|JsonResponse {
        self::authorizeProject(null);

        $validated = $request->validate([...$this->rules(), 'format' => ['nullable', 'in:pdf,portal,mail']]);
        $invoice = $this->draft($validated);

        return match ($validated['format'] ?? 'pdf') {
            'portal' => response()->json(
                InvoiceDocument::forInvoice($invoice, $mergeData->forInvoice($invoice))->page(),
            ),
            'mail' => response(MailPreview::html(
                new InvoiceSent($invoice, '', InvoiceSent::defaultMessage($invoice)),
                $invoice->contact ?? (object) [],
            ), 200, ['Content-Type' => 'text/html']),
            default => response($render->forInvoice($invoice), 200, ['Content-Type' => 'text/html']),
        };
    }

    /** @return array<string, list<mixed>> */
    private function rules(): array
    {
        return [
            'contact_id' => ['nullable', 'string'],
            'contact.name' => ['nullable', 'string', 'max:255'],
            'contact.email' => ['nullable', 'email', 'max:255'],
            'contact.street' => ['nullable', 'string', 'max:255'],
            'contact.postcode' => ['nullable', 'string', 'max:20'],
            'contact.city' => ['nullable', 'string', 'max:255'],
            'case_id' => ['nullable', 'string'],
            'issued_on' => ['nullable', 'date', 'before_or_equal:today'],
            'due_on' => ['nullable', 'date'],
            'intro' => ['nullable', 'string', 'max:20000'],
            'outro' => ['nullable', 'string', 'max:20000'],
            'discount_type' => ['nullable', 'in:percentage,amount'],
            'discount_value' => ['nullable', 'integer', 'min:1'],
            'items' => ['nullable', 'array'],
            'items.*.title' => ['nullable', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:2000'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'integer', 'min:0'],
            'items.*.discount_type' => ['nullable', 'in:percentage,amount'],
            'items.*.discount_value' => ['nullable', 'integer', 'min:1'],
            'items.*.vat_rate' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'items.*.installment_id' => ['nullable', 'string'],
            'items.*.quote_item_id' => ['nullable', 'string'],
        ];
    }

    /** @param  array<string, mixed>  $validated */
    private function case(array $validated): ?ClientCase
    {
        if (! isset($validated['case_id'])) {
            return null;
        }

        $project = ClientCase::query()->where('public_id', $validated['case_id'])->firstOrFail();
        self::authorizeProject($project);

        return $project;
    }

    /**
     * The request's items as IssueInvoice::positions() takes them. A rate named
     * by a stale form is a 404 on issue and simply left out of a preview.
     *
     * @param  array<string, mixed>  $validated
     * @return list<array<string, mixed>>
     */
    private function positions(array $validated, bool $strict): array
    {
        $ids = collect($validated['items'] ?? [])->pluck('installment_id')->filter();
        $rows = PaymentInstallment::query()->whereIn('public_id', $ids)->with('plan.case')->get()->keyBy('public_id');

        abort_if($strict && $rows->count() !== $ids->unique()->count(), 404);

        // A position may only name a line of the accepted quote of the project it is billed to.
        $lineIds = collect($validated['items'] ?? [])->pluck('quote_item_id')->filter();
        $lines = QuoteItem::query()
            ->whereIn('public_id', $lineIds)
            ->whereHas('quote', fn ($quote) => $quote
                ->where('status', QuoteStatus::Accepted)
                ->whereHas('case', fn ($project) => $project->where('public_id', $validated['case_id'] ?? null)))
            ->get()
            ->keyBy('public_id');

        abort_if($strict && $lines->count() !== $lineIds->unique()->count(), 404);

        return collect($validated['items'] ?? [])
            ->reject(fn (array $item) => isset($item['installment_id']) && ! $rows->has($item['installment_id']))
            ->map(fn (array $item) => [
                'title' => $item['title'] ?? '',
                'description' => $item['description'] ?? null,
                'quantity' => $item['quantity'] ?? 1,
                'unit_price' => $item['unit_price'] ?? 0,
                'discount_type' => $item['discount_type'] ?? null,
                'discount_value' => $item['discount_value'] ?? null,
                'vat_rate' => $item['vat_rate'] ?? null,
                'installment' => isset($item['installment_id']) ? $rows[$item['installment_id']] : null,
                'quote_item_id' => isset($item['quote_item_id']) ? $lines[$item['quote_item_id']]?->id : null,
            ])
            ->values()
            ->all();
    }

    /**
     * An unsaved invoice from the create form: nothing is written and no
     * number drawn, and every field may still be half filled.
     *
     * @param  array<string, mixed>  $validated
     */
    private function draft(array $validated): Invoice
    {
        $project = $this->case($validated);

        $invoice = new Invoice([
            'number' => __('payments.previewNumber'),
            'issued_at' => isset($validated['issued_on']) ? now()->setDateFrom($validated['issued_on']) : now(),
            'intro' => InlineHtmlSanitizer::letter($validated['intro'] ?? null),
            'outro' => InlineHtmlSanitizer::letter($validated['outro'] ?? null),
            'discount_type' => $validated['discount_type'] ?? null,
            'discount_value' => $validated['discount_value'] ?? null,
        ]);
        $invoice->case_id = $project?->id;

        $items = collect($this->positions($validated, strict: false))->map(function (array $position) {
            $item = new InvoiceItem(collect($position)->except('installment')->all());
            $item->payment_installment_id = $position['installment']?->id;

            return $item->setRelation('installment', $position['installment']);
        });

        $free = $items->whereNull('payment_installment_id');
        $installments = $items->pluck('installment')->filter()->values();

        if ($free->isNotEmpty()) {
            $installments->push(new PaymentInstallment([
                'amount' => InvoiceFigures::forPositions($free, $invoice->discount_type, $invoice->discount_value)->gross,
                'due_on' => $validated['due_on'] ?? null,
                'status' => PaymentInstallmentStatus::Invoiced,
            ]));
        }

        $contact = match (true) {
            isset($validated['contact_id']) => Contact::query()->where('public_id', $validated['contact_id'])->first(),
            isset($validated['contact']['name']) => new Contact($validated['contact']),
            default => $project?->primaryContact(),
        };

        return $invoice
            ->setRelation('creditNotes', collect())
            ->setRelation('template', null)
            ->setRelation('case', $project)
            ->setRelation('contact', $contact)
            ->setRelation('items', $items)
            ->setRelation('installments', $installments);
    }

    /**
     * Inline rather than an attachment: the browser's own viewer is what a studio
     * wants when checking its own paperwork, and the client-side `download`
     * attribute still saves it.
     */
    public function download(Invoice $invoice): StreamedResponse
    {
        self::authorizeProject($invoice->case);

        abort_if($invoice->pdf_path === null, 404);

        return Storage::disk('local')->response($invoice->pdf_path, "rechnung-{$invoice->number}.pdf");
    }

    /**
     * #415's compose screen, after the blueprint in SendByMailView.vue: the
     * recipient, the subject and the message on the left, this notification's
     * own render on the right.
     */
    public function sendView(Invoice $invoice): Response
    {
        self::authorizeProject($invoice->case);

        $recipients = $this->recipients($invoice);

        return Inertia::render('Invoices/Send', [
            'invoiceId' => $invoice->public_id,
            'invoice' => [
                'number' => $invoice->number,
                'date' => $invoice->issued_at->format('d.m.Y'),
                'amount' => $invoice->amount(),
                'statusLabel' => __("payments.invoiceStatus.{$invoice->status()}"),
            ],
            'recipients' => $recipients->map(fn (Contact $contact) => ['name' => $contact->name, 'email' => $contact->email])->values(),
            'subject' => InvoiceSent::defaultSubject($invoice),
            'message' => InvoiceSent::defaultMessage($invoice),
            'mailTemplates' => MailTemplateData::forCompose($invoice->case?->primaryShoot?->kind, app(InvoiceMergeData::class)->forInvoice($invoice)),
            'mergeFields' => collect(MergeFieldVocabulary::samples())->map(fn (string $sample, string $path) => ['path' => $path, 'sample' => $sample])->values()->all(),
            'shootKinds' => Shoot::kindSuggestions(),
            'occasion' => 'invoice_sent',
            'mailHtml' => MailPreview::html(
                new InvoiceSent($invoice, '', MailPreview::MESSAGE),
                $recipients->first() ?? (object) [],
            ),
        ]);
    }

    /**
     * No automatic send on issue (#415): the photographer decides when the
     * mail goes. `sent_at` records the first one and is not moved by a
     * second — it answers when the client was told, not how often.
     */
    public function send(Request $request, Invoice $invoice, NotificationService $notifications): RedirectResponse
    {
        self::authorizeProject($invoice->case);

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['nullable', 'string'],
        ]);

        foreach ($this->recipients($invoice) as $contact) {
            $notifications->send($contact, new InvoiceSent($invoice, $validated['subject'], $validated['message'] ?? null));
        }

        if ($invoice->sent_at === null) {
            $invoice->update(['sent_at' => now()]);
        }

        return redirect('/rechnungen');
    }

    public function cancel(Request $request, Invoice $invoice, CancelInvoice $cancel): RedirectResponse
    {
        self::authorizeProject($invoice->case);

        $validated = $request->validate([
            'kind' => ['required', Rule::enum(CancellationKind::class)],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $cancel($invoice, CancellationKind::from($validated['kind']), $validated['reason'], auth('staff')->user());

        return back();
    }

    /** The Gutschrift written on the invoice's own positions; what it may take back is what was paid and not yet credited. */
    public function creditNoteForm(Invoice $invoice): Response
    {
        self::authorizeProject($invoice->case);

        $creditable = $invoice->paidAmount() - $invoice->creditedAmount();

        abort_if($invoice->isCancelled() || $creditable <= 0, 404);

        return Inertia::render('Invoices/CreditNote', [
            'invoice' => InvoiceDetailData::fromModel($invoice),
            'creditable' => $creditable,
            'studioName' => StudioSettings::current()->display_name,
        ]);
    }

    public function creditNote(Request $request, Invoice $invoice, IssueCreditNote $issue): RedirectResponse
    {
        self::authorizeProject($invoice->case);

        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:1000'],
            'send' => ['sometimes', 'boolean'],
        ]);

        $note = $issue($invoice, $validated['amount'], $validated['reason'], auth('staff')->user());

        if ($request->boolean('send')) {
            foreach ($this->recipients($invoice) as $contact) {
                app(NotificationService::class)->send($contact, new CreditNoteSent($note));
            }
        }

        return back();
    }

    public function downloadCreditNote(Invoice $invoice, CreditNote $creditNote): StreamedResponse
    {
        self::authorizeProject($invoice->case);

        abort_if($creditNote->invoice_id !== $invoice->id || $creditNote->pdf_path === null, 404);

        return Storage::disk('local')->response($creditNote->pdf_path, "gutschrift-{$creditNote->number}.pdf");
    }

    /**
     * ClientCasePolicy::managePaymentPlan where there is a project; the same
     * capability without one, since nothing narrows who may see it.
     */
    public static function authorizeProject(?ClientCase $project): void
    {
        $user = auth('staff')->user();

        abort_unless(
            $project === null ? $user->can(Capability::ManageContracts->value) : $user->can('managePaymentPlan', $project),
            403,
        );
    }

    /**
     * The invoice's own recipient — § 14 UStG names one Leistungsempfänger.
     * Empty where they have no mail address, which the compose screen shows.
     *
     * @return Collection<int, Contact>
     */
    private function recipients(Invoice $invoice)
    {
        return filled($invoice->contact?->email) ? collect([$invoice->contact]) : collect();
    }
}
