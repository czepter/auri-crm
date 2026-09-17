<?php

namespace App\Payments\Http\Controllers;

use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Support\DocumentMergeDataBuilder;
use App\Documents\Support\InlineHtmlSanitizer;
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
        ]);
    }

    public function create(Request $request, StudioSettingsResolver $settings, RenderInvoicePdf $render): Response
    {
        self::authorizeProject(null);

        $viewer = $request->user('staff');

        $plans = PaymentPlan::query()
            ->whereNull('settled_at')
            ->whereHas('installments', fn ($query) => $query->whereNull('invoice_id')->whereNull('cancelled_at'))
            ->whereHas('job.project', fn ($query) => $query->visibleTo($viewer))
            ->with(['installments', 'job.project'])
            ->get()
            ->map(fn (PaymentPlan $plan) => [
                'projectId' => $plan->job->project->public_id,
                'projectName' => $plan->job->project->name,
                'installments' => collect(PaymentPlanData::fromModel($plan)->installments)
                    ->filter(fn ($row) => $row->invoiceNumber === null && $row->status !== 'cancelled')
                    ->map(fn ($row) => ['id' => $row->id, 'label' => $row->label, 'amount' => $row->amount, 'dueOn' => $row->dueOn])
                    ->values(),
            ])
            ->sortBy('projectName')
            ->values();

        return Inertia::render('Invoices/Create', [
            'plans' => $plans,
            'project' => $request->query('projekt'),
            'vatRate' => $settings->vatMode() === 'small_business' ? 0 : $settings->effectiveVatRate(),
            // The invoice template's prose: the starting point for this
            // invoice's own letter, merge fields left for the render.
            'intro' => InlineHtmlSanitizer::letter($render->currentTemplate(DocumentTemplateType::Invoice)?->body) ?? '',
        ]);
    }

    public function store(Request $request, IssueInvoice $issue): RedirectResponse
    {
        self::authorizeProject(null);

        $actor = $request->user('staff');

        if ($request->filled('installments')) {
            $ids = $request->validate(['installments' => ['required', 'array', 'min:1'], 'installments.*' => ['string']])['installments'];
            $installments = PaymentInstallment::query()->whereIn('public_id', $ids)->with('plan.job.project')->get();

            abort_if($installments->count() !== count($ids), 404);
            self::authorizeProject($installments->first()->project());

            return redirect("/rechnungen/{$issue($installments, $actor, $this->texts($request))->public_id}/versenden");
        }

        $validated = $request->validate([
            'contact_id' => ['nullable', 'string'],
            'contact.name' => ['required_without:contact_id', 'nullable', 'string', 'max:255'],
            'contact.email' => ['nullable', 'email', 'max:255'],
            'contact.street' => ['nullable', 'string', 'max:255'],
            'contact.postcode' => ['nullable', 'string', 'max:20'],
            'contact.city' => ['nullable', 'string', 'max:255'],
            'due_on' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'integer', 'min:0'],
            'items.*.vat_rate' => ['required', 'integer', 'min:0', 'max:10000'],
        ]);

        $contact = isset($validated['contact_id'])
            ? Contact::query()->where('public_id', $validated['contact_id'])->firstOrFail()
            : Contact::create(array_filter($validated['contact'], fn ($value) => $value !== null));

        $invoice = $issue->free($contact, $validated['items'], $validated['due_on'], $actor, $this->texts($request));

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

        $validated = $request->validate([
            'format' => ['nullable', 'in:pdf,portal,mail'],
            'installments' => ['nullable', 'array'],
            'installments.*' => ['string'],
            'contact_id' => ['nullable', 'string'],
            'contact.name' => ['nullable', 'string', 'max:255'],
            'contact.street' => ['nullable', 'string', 'max:255'],
            'contact.postcode' => ['nullable', 'string', 'max:20'],
            'contact.city' => ['nullable', 'string', 'max:255'],
            'due_on' => ['nullable', 'date'],
            'intro' => ['nullable', 'string', 'max:20000'],
            'outro' => ['nullable', 'string', 'max:20000'],
            'items' => ['nullable', 'array'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.unit_price' => ['nullable', 'integer', 'min:0'],
            'items.*.vat_rate' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ]);

        $invoice = $this->draft($validated);

        return match ($validated['format'] ?? 'pdf') {
            'portal' => response()->json(
                InvoiceDocument::forInvoice($invoice, $mergeData->fromInvoice($invoice, $settings), $settings)->page(),
            ),
            'mail' => response(MailPreview::html(
                new InvoiceSent($invoice, '', MailPreview::MESSAGE, $settings),
                $invoice->contact ?? (object) [],
            ), 200, ['Content-Type' => 'text/html']),
            default => response($render->forInvoice($invoice), 200, ['Content-Type' => 'text/html']),
        };
    }

    /**
     * An unsaved invoice from the create form: nothing is written and no
     * number drawn, and every field may still be half filled.
     *
     * @param  array<string, mixed>  $validated
     */
    private function draft(array $validated): Invoice
    {
        $invoice = new Invoice([
            'number' => __('payments.previewNumber'),
            'issued_at' => now(),
            'intro' => InlineHtmlSanitizer::letter($validated['intro'] ?? null),
            'outro' => InlineHtmlSanitizer::letter($validated['outro'] ?? null),
        ]);
        $invoice->setRelation('creditNotes', collect())->setRelation('template', null);

        if (filled($validated['installments'] ?? null)) {
            $installments = PaymentInstallment::query()
                ->whereIn('public_id', $validated['installments'])
                ->whereNotNull('payment_plan_id')
                ->with('plan.job.project')
                ->orderBy('sort_order')
                ->get();
            $project = $installments->first()?->plan->job->project;

            abort_if($project === null, 404);
            self::authorizeProject($project);

            $invoice->project_id = $project->id;

            return $invoice
                ->setRelation('project', $project)
                ->setRelation('contact', $project->primaryContact())
                ->setRelation('installments', $installments)
                ->setRelation('items', collect());
        }

        $items = collect($validated['items'] ?? [])->map(fn (array $item) => new InvoiceItem([
            'description' => $item['description'] ?? '',
            'quantity' => $item['quantity'] ?? 0,
            'unit_price' => $item['unit_price'] ?? 0,
            'vat_rate' => $item['vat_rate'] ?? 0,
        ]));

        $contact = isset($validated['contact_id'])
            ? Contact::query()->where('public_id', $validated['contact_id'])->first()
            : new Contact($validated['contact'] ?? []);

        return $invoice
            ->setRelation('project', null)
            ->setRelation('contact', $contact)
            ->setRelation('items', $items)
            ->setRelation('installments', collect([new PaymentInstallment([
                'amount' => $items->sum(fn (InvoiceItem $item) => $item->total()),
                'due_on' => $validated['due_on'] ?? null,
                'status' => PaymentInstallmentStatus::Invoiced,
            ])]));
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

    /** @return array{intro?: ?string, outro?: ?string} */
    private function texts(Request $request): array
    {
        return array_map(InlineHtmlSanitizer::letter(...), $request->validate([
            'intro' => ['nullable', 'string', 'max:20000'],
            'outro' => ['nullable', 'string', 'max:20000'],
        ]));
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
