<?php

namespace App\Payments\Http\Controllers;

use App\Crm\Models\Contact;
use App\Http\Controllers\Controller;
use App\Notifications\NotificationService;
use App\Notifications\Support\MailPreview;
use App\Payments\Actions\CancelInvoice;
use App\Payments\Actions\IssueCreditNote;
use App\Payments\Actions\IssueInvoice;
use App\Payments\Actions\MarkInstallmentPaid;
use App\Payments\Enums\CancellationKind;
use App\Payments\Enums\SettledVia;
use App\Payments\Models\CreditNote;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Notifications\InvoiceSent;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * T-07's staff action: mark one installment paid, with the date, method and
 * reference staff actually have in hand — never a status flipped on its own.
 * Reuses managePaymentPlan (App\Policies\ProjectPolicy) rather than a new
 * capability: marking a payment settled is exactly as sensitive as creating
 * or editing the plan it belongs to.
 */
class PaymentInstallmentController extends Controller
{
    public function markPaid(Request $request, PaymentInstallment $installment, MarkInstallmentPaid $markPaid): RedirectResponse
    {
        $this->authorize('managePaymentPlan', $installment->plan->job->project);

        $validated = $request->validate([
            'paid_on' => ['required', 'date'],
            'method' => ['required', Rule::enum(SettledVia::class)],
            'reference' => ['nullable', 'string', 'max:255'],
            'amount' => ['nullable', 'integer', 'min:1'],
        ]);

        $markPaid(
            $installment,
            SettledVia::from($validated['method']),
            $validated['paid_on'],
            $validated['reference'] ?? null,
            auth('staff')->user(),
            $validated['amount'] ?? null,
        );

        return back();
    }

    /**
     * M-06: issue the invoice for one installment. Same authority as marking
     * it paid — both are statements the studio makes about money owed on a
     * job it already booked, and splitting them across two capabilities would
     * invent a role that can settle a payment but not document it.
     */
    public function issueInvoice(PaymentInstallment $installment, IssueInvoice $issue): RedirectResponse
    {
        $this->authorize('managePaymentPlan', $installment->plan->job->project);

        $issue($installment, auth('staff')->user());

        // #415: straight into the compose screen. Sending stays a second decision — see send().
        return redirect("/raten/{$installment->public_id}/rechnung/versenden");
    }

    /**
     * #415's compose screen, after the blueprint in SendByMailView.vue: the
     * recipients, the subject and the message on the left, this
     * notification's own render on the right.
     */
    public function sendView(PaymentInstallment $installment, StudioSettingsResolver $settings): Response
    {
        $this->authorize('managePaymentPlan', $installment->plan->job->project);

        abort_unless($installment->isInvoiced(), 404);

        $recipients = $this->clients($installment);

        return Inertia::render('Invoices/Send', [
            'projectId' => $installment->plan->job->project->public_id,
            'installmentId' => $installment->public_id,
            'invoice' => [
                'number' => $installment->invoice_number,
                'date' => $installment->invoiced_at?->format('d.m.Y'),
                'amount' => (int) $installment->amount,
                'statusLabel' => $installment->status->label(),
            ],
            'recipients' => $recipients
                ->map(fn ($contact) => ['name' => $contact->name, 'email' => $contact->email])
                ->values(),
            'mailHtml' => MailPreview::html(
                new InvoiceSent($installment, '', MailPreview::MESSAGE, $settings),
                $recipients->first() ?? (object) [],
            ),
        ]);
    }

    /**
     * No automatic send on issue (#415): the photographer decides when the
     * mail goes. `sent_at` records the first one and is not moved by a
     * second — it answers when the client was told, not how often.
     */
    public function send(
        Request $request,
        PaymentInstallment $installment,
        NotificationService $notifications,
        StudioSettingsResolver $settings,
    ): RedirectResponse {
        $this->authorize('managePaymentPlan', $installment->plan->job->project);

        abort_unless($installment->isInvoiced(), 404);

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['nullable', 'string'],
        ]);

        foreach ($this->clients($installment) as $contact) {
            $notifications->send($contact, new InvoiceSent($installment, $validated['subject'], $validated['message'] ?? null, $settings));
        }

        if ($installment->sent_at === null) {
            $installment->update(['sent_at' => now()]);
        }

        return redirect("/auftraege/{$installment->plan->job->project->public_id}");
    }

    /**
     * The primary contact and nobody else — § 14 UStG names one
     * Leistungsempfänger, and DocumentMergeDataBuilder already addresses the
     * document to exactly this person. Empty where they have no mail address,
     * which the compose screen shows.
     *
     * @return Collection<int, Contact>
     */
    private function clients(PaymentInstallment $installment): Collection
    {
        $primary = $installment->plan->job->project->primaryContact();

        return filled($primary?->email) ? collect([$primary]) : collect();
    }

    /**
     * #408. Same authority again: retiring an invoice is a statement about
     * money owed, exactly as issuing it was.
     */
    public function cancelInvoice(Request $request, PaymentInstallment $installment, CancelInvoice $cancel): RedirectResponse
    {
        $this->authorize('managePaymentPlan', $installment->plan->job->project);

        $validated = $request->validate([
            'kind' => ['required', Rule::enum(CancellationKind::class)],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $cancel(
            $installment,
            CancellationKind::from($validated['kind']),
            $validated['reason'],
            auth('staff')->user(),
        );

        return back();
    }

    /**
     * #409. The euro amount arrives as the client typed it and is converted
     * here, the same boundary the payment-plan form already crosses.
     */
    public function issueCreditNote(Request $request, PaymentInstallment $installment, IssueCreditNote $issue): RedirectResponse
    {
        $this->authorize('managePaymentPlan', $installment->plan->job->project);

        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $issue($installment, $validated['amount'], $validated['reason'], auth('staff')->user());

        return back();
    }

    public function downloadCreditNote(PaymentInstallment $installment, CreditNote $creditNote): StreamedResponse
    {
        $this->authorize('managePaymentPlan', $installment->plan->job->project);

        abort_if($creditNote->payment_installment_id !== $installment->id || $creditNote->pdf_path === null, 404);

        return Storage::disk('local')->response($creditNote->pdf_path, "gutschrift-{$creditNote->number}.pdf");
    }

    /**
     * Inline rather than an attachment: the browser's own viewer is what a studio
     * wants when checking its own paperwork, and the client-side `download`
     * attribute still saves it.
     */
    public function downloadInvoice(PaymentInstallment $installment): StreamedResponse
    {
        $this->authorize('managePaymentPlan', $installment->plan->job->project);

        abort_if($installment->invoice_path === null, 404);

        return Storage::disk('local')->response(
            $installment->invoice_path,
            "rechnung-{$installment->invoice_number}.pdf",
        );
    }
}
