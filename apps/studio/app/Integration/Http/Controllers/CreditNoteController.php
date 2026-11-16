<?php

namespace App\Integration\Http\Controllers;

use App\Documents\Support\InlineHtmlSanitizer;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\NotificationService;
use App\Payments\Actions\IssueCreditNote;
use App\Payments\Data\CreditNoteRowData;
use App\Payments\Http\Controllers\InvoiceController as AdminInvoiceController;
use App\Payments\Models\CreditNote;
use App\Payments\Models\CreditNoteDraft;
use App\Payments\Models\Invoice;
use App\Payments\Notifications\CreditNoteSent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** The Gutschrift on the api surface: issued through IssueCreditNote, its draft kept one per invoice as the admin builder keeps it. */
class CreditNoteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = CreditNote::query()
            ->when($request->user('api') instanceof User, fn (Builder $q) => $q->whereHas('invoice', fn (Builder $invoice) => $invoice
                ->where(fn (Builder $q) => $q->whereNull('case_id')
                    ->orWhereHas('case', fn (Builder $case) => $case->visibleTo($request->user('api'))))))
            ->with(['invoice.case', 'invoice.contact']);

        return CrmReadController::page($query, $request, CreditNoteRowData::class);
    }

    public function store(Request $request, Invoice $invoice, IssueCreditNote $issue, NotificationService $notifications): JsonResponse
    {
        $rules = AdminInvoiceController::creditNoteRules();

        $validated = $request->validate([
            ...$rules,
            'issued_on' => [...$rules['issued_on'], fn (string $attribute, mixed $value, \Closure $fail) => AdminInvoiceController::notBeforeLatestCreditNote($invoice, $attribute, $value, $fail)],
            'amount' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:1000'],
            'send' => ['sometimes', 'boolean'],
        ]);

        $note = $issue(
            $invoice,
            $validated['amount'],
            $validated['reason'],
            $request->user('api') instanceof User ? $request->user('api') : null,
            $validated['issued_on'] ?? null,
            InlineHtmlSanitizer::letter($validated['intro'] ?? null),
            InlineHtmlSanitizer::letter($validated['outro'] ?? null),
        );

        $invoice->creditNoteDraft()->delete();

        if ($request->boolean('send') && filled($invoice->contact?->email)) {
            $notifications->send($invoice->contact, new CreditNoteSent($note));
        }

        return response()->json(['data' => CreditNoteRowData::fromModel($note->load('invoice.case', 'invoice.contact'))], 201);
    }

    public function showDraft(Invoice $invoice): JsonResponse
    {
        return response()->json(['data' => $invoice->creditNoteDraft?->payload]);
    }

    public function saveDraft(Request $request, Invoice $invoice): JsonResponse
    {
        $validated = $request->validate([
            ...AdminInvoiceController::creditNoteRules(),
            'taken' => ['array'],
            'taken.*' => ['boolean'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'send' => ['boolean'],
        ]);

        $draft = CreditNoteDraft::updateOrCreate(
            ['invoice_id' => $invoice->id],
            ['payload' => $validated, 'created_by' => $request->user('api') instanceof User ? $request->user('api')->id : null],
        );

        return response()->json(['data' => $draft->payload]);
    }

    public function destroyDraft(Invoice $invoice): Response
    {
        $invoice->creditNoteDraft()->delete();

        return response()->noContent();
    }
}
