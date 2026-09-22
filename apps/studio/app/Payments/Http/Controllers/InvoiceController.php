<?php

namespace App\Payments\Http\Controllers;

use App\Catalog\Data\CatalogItemData;
use App\Catalog\Data\PackageData;
use App\Catalog\Models\CatalogItem;
use App\Catalog\Models\Package;
use App\Crm\Data\ProjectSuggestionData;
use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use App\Documents\Actions\SeedStarterTemplates;
use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Support\DocumentMergeDataBuilder;
use App\Documents\Support\InlineHtmlSanitizer;
use App\Documents\Support\StarterTemplateCatalog;
use App\Enums\Capability;
use App\Http\Controllers\Controller;
use App\Notifications\NotificationService;
use App\Notifications\Support\MailPreview;
use App\Payments\Actions\CancelInvoice;
use App\Payments\Actions\IssueCreditNote;
use App\Payments\Actions\IssueInvoice;
use App\Payments\Actions\RenderInvoicePdf;
use App\Payments\Data\InvoiceRowData;
use App\Payments\Data\PaymentPlanData;
use App\Payments\Enums\CancellationKind;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Models\CreditNote;
use App\Payments\Models\Invoice;
use App\Payments\Models\InvoiceItem;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Models\PaymentPlan;
use App\Payments\Notifications\InvoiceSent;
use App\Payments\Support\InvoiceDocument;
use App\Payments\Support\InvoiceFigures;
use App\Settings\Data\StudioSettingsData;
use App\Settings\Support\StudioReadiness;
use App\Settings\Support\StudioSettingsResolver;
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
            ->where(fn ($query) => $query->whereNull('project_id')->orWhereHas('project', fn ($project) => $project->visibleTo($viewer)))
            ->with(['installments', 'creditNotes', 'contact', 'project'])
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('Invoices/Index', [
            'invoices' => InvoiceRowData::collect($invoices->map(InvoiceRowData::fromModel(...))),
            // The export route is gated by ManageSettings, so the years appear only for roles it lets in.
            'exportYears' => $viewer->role->allows(Capability::ManageSettings)
                ? Invoice::query()->pluck('issued_at')->map(fn ($at) => (int) $at->format('Y'))->unique()->sortDesc()->values()
                : [],
        ]);
    }

    public function create(
        Request $request,
        StudioSettingsResolver $settings,
        RenderInvoicePdf $render,
        SeedStarterTemplates $seed,
        StudioReadiness $readiness,
    ): Response {
        self::authorizeProject(null);

        // Die Rechnungsvorlage entsteht hier statt beim Abschluss des
        // Assistenten: erst jetzt steht fest, ob sie eine USt.-Zeile trägt.
        // SeedStarterTemplates dedupliziert über Typ und Name, ein zweiter
        // Aufruf schreibt also nichts.
        $seed(
            StarterTemplateCatalog::invoiceDefaults($settings->vatMode()),
            $request->user('staff'),
            $settings->clientRegister(),
        );

        $viewer = $request->user('staff');

        // The Schnellwahl: every rate still to be billed, keyed by project.
        $rates = PaymentPlan::query()
            ->whereNull('settled_at')
            ->whereHas('installments', fn ($query) => $query->whereNull('invoice_id')->whereNull('cancelled_at'))
            ->whereHas('job.project', fn ($query) => $query->visibleTo($viewer))
            ->with(['installments', 'job.project'])
            ->get()
            ->mapWithKeys(fn (PaymentPlan $plan) => [
                $plan->job->project->public_id => collect(PaymentPlanData::fromModel($plan)->installments)
                    ->filter(fn ($row) => $row->invoiceNumber === null && $row->status !== 'cancelled')
                    ->map(fn ($row) => ['id' => $row->id, 'label' => $row->label, 'amount' => $row->amount, 'dueOn' => $row->dueOn])
                    ->values(),
            ]);

        $project = $request->query('projekt') === null
            ? null
            : Project::query()->visibleTo($viewer)->where('public_id', $request->query('projekt'))->first();

        return Inertia::render('Invoices/Create', [
            'rates' => $rates,
            'project' => $project === null ? null : ProjectSuggestionData::fromModel($project),
            'packages' => PackageData::collect(Package::active()->with('items')->orderBy('name')->get()),
            'catalogItems' => CatalogItemData::collect(CatalogItem::query()->orderBy('title')->get()),
            'vatSettings' => [
                'mode' => $settings->vatMode(),
                'rate' => $settings->effectiveVatRate(),
                'note' => $settings->vatNote(),
            ],
            // The invoice template's prose: the starting point for this
            // invoice's own letter, merge fields left for the render.
            'intro' => InlineHtmlSanitizer::letter($render->currentTemplate(DocumentTemplateType::Invoice)?->body) ?? '',
            'studioSettings' => StudioSettingsData::fromResolver($settings),
            // Was IssueInvoice beim Ausstellen ohnehin verlangt, steht hier
            // vor dem Formular statt hinter dem Absenden.
            'missingInvoiceData' => $readiness->missingForInvoice(),
        ]);
    }

    public function store(Request $request, IssueInvoice $issue): RedirectResponse
    {
        self::authorizeProject(null);

        $validated = $request->validate([
            ...$this->rules(),
            'contact.name' => ['required_without_all:contact_id,project_id', 'nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.title' => ['required', 'string', 'max:255'],
        ]);

        $project = $this->project($validated);
        $contact = match (true) {
            isset($validated['contact_id']) => Contact::query()->where('public_id', $validated['contact_id'])->firstOrFail(),
            isset($validated['contact']['name']) => Contact::create(array_filter($validated['contact'], fn ($value) => $value !== null)),
            default => $project?->primaryContact(),
        };

        $invoice = $issue->positions(
            $contact,
            $project,
            $this->positions($validated, strict: true),
            $validated['due_on'] ?? null,
            [
                'intro' => InlineHtmlSanitizer::letter($validated['intro'] ?? null),
                'outro' => InlineHtmlSanitizer::letter($validated['outro'] ?? null),
                'discount_type' => $validated['discount_type'] ?? null,
                'discount_value' => $validated['discount_value'] ?? null,
            ],
            $request->user('staff'),
        );

        return redirect("/rechnungen/{$invoice->public_id}/versenden");
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
        DocumentMergeDataBuilder $mergeData,
        StudioSettingsResolver $settings,
    ): HttpResponse|JsonResponse {
        self::authorizeProject(null);

        $validated = $request->validate([...$this->rules(), 'format' => ['nullable', 'in:pdf,portal,mail']]);
        $invoice = $this->draft($validated, $settings);

        return match ($validated['format'] ?? 'pdf') {
            'portal' => response()->json(
                InvoiceDocument::forInvoice($invoice, $mergeData->fromInvoice($invoice, $settings), $settings)->page(),
            ),
            'mail' => response(MailPreview::html(
                new InvoiceSent($invoice, '', InvoiceSent::defaultMessage($invoice), $settings),
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
            'project_id' => ['nullable', 'string'],
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
        ];
    }

    /** @param  array<string, mixed>  $validated */
    private function project(array $validated): ?Project
    {
        if (! isset($validated['project_id'])) {
            return null;
        }

        $project = Project::query()->where('public_id', $validated['project_id'])->firstOrFail();
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
        $rows = PaymentInstallment::query()->whereIn('public_id', $ids)->with('plan.job.project')->get()->keyBy('public_id');

        abort_if($strict && $rows->count() !== $ids->unique()->count(), 404);

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
    private function draft(array $validated, StudioSettingsResolver $settings): Invoice
    {
        $project = $this->project($validated);

        $invoice = new Invoice([
            'number' => __('payments.previewNumber'),
            'issued_at' => now(),
            'intro' => InlineHtmlSanitizer::letter($validated['intro'] ?? null),
            'outro' => InlineHtmlSanitizer::letter($validated['outro'] ?? null),
            'discount_type' => $validated['discount_type'] ?? null,
            'discount_value' => $validated['discount_value'] ?? null,
        ]);
        $invoice->project_id = $project?->id;

        $items = collect($this->positions($validated, strict: false))->map(function (array $position) {
            $item = new InvoiceItem(collect($position)->except('installment')->all());
            $item->payment_installment_id = $position['installment']?->id;

            return $item->setRelation('installment', $position['installment']);
        });

        $free = $items->whereNull('payment_installment_id');
        $installments = $items->pluck('installment')->filter()->values();

        if ($free->isNotEmpty()) {
            $installments->push(new PaymentInstallment([
                'amount' => InvoiceFigures::forPositions($free, $invoice->discount_type, $invoice->discount_value, $settings)->gross,
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
            ->setRelation('project', $project)
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
        self::authorizeProject($invoice->project);

        abort_if($invoice->pdf_path === null, 404);

        return Storage::disk('local')->response($invoice->pdf_path, "rechnung-{$invoice->number}.pdf");
    }

    /**
     * #415's compose screen, after the blueprint in SendByMailView.vue: the
     * recipient, the subject and the message on the left, this notification's
     * own render on the right.
     */
    public function sendView(Invoice $invoice, StudioSettingsResolver $settings): Response
    {
        self::authorizeProject($invoice->project);

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
            'message' => InvoiceSent::defaultMessage($invoice),
            'mailHtml' => MailPreview::html(
                new InvoiceSent($invoice, '', MailPreview::MESSAGE, $settings),
                $recipients->first() ?? (object) [],
            ),
        ]);
    }

    /**
     * No automatic send on issue (#415): the photographer decides when the
     * mail goes. `sent_at` records the first one and is not moved by a
     * second — it answers when the client was told, not how often.
     */
    public function send(Request $request, Invoice $invoice, NotificationService $notifications, StudioSettingsResolver $settings): RedirectResponse
    {
        self::authorizeProject($invoice->project);

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['nullable', 'string'],
        ]);

        foreach ($this->recipients($invoice) as $contact) {
            $notifications->send($contact, new InvoiceSent($invoice, $validated['subject'], $validated['message'] ?? null, $settings));
        }

        if ($invoice->sent_at === null) {
            $invoice->update(['sent_at' => now()]);
        }

        return redirect('/rechnungen');
    }

    public function cancel(Request $request, Invoice $invoice, CancelInvoice $cancel): RedirectResponse
    {
        self::authorizeProject($invoice->project);

        $validated = $request->validate([
            'kind' => ['required', Rule::enum(CancellationKind::class)],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $cancel($invoice, CancellationKind::from($validated['kind']), $validated['reason'], auth('staff')->user());

        return back();
    }

    public function creditNote(Request $request, Invoice $invoice, IssueCreditNote $issue): RedirectResponse
    {
        self::authorizeProject($invoice->project);

        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $issue($invoice, $validated['amount'], $validated['reason'], auth('staff')->user());

        return back();
    }

    public function downloadCreditNote(Invoice $invoice, CreditNote $creditNote): StreamedResponse
    {
        self::authorizeProject($invoice->project);

        abort_if($creditNote->invoice_id !== $invoice->id || $creditNote->pdf_path === null, 404);

        return Storage::disk('local')->response($creditNote->pdf_path, "gutschrift-{$creditNote->number}.pdf");
    }

    /**
     * ProjectPolicy::managePaymentPlan where there is a project; the same
     * capability without one, since nothing narrows who may see it.
     */
    public static function authorizeProject(?Project $project): void
    {
        $user = auth('staff')->user();

        abort_unless(
            $project === null ? $user->role->allows(Capability::ManageContracts) : $user->can('managePaymentPlan', $project),
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
