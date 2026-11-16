<?php

namespace App\Integration\Http\Controllers;

use App\Crm\Actions\CreateContact;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Documents\Support\InlineHtmlSanitizer;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Payments\Actions\IssueInvoice;
use App\Payments\Data\InvoiceDetailData;
use App\Payments\Data\InvoiceRowData;
use App\Payments\Http\Controllers\InvoiceController as AdminInvoiceController;
use App\Payments\Models\Invoice;
use App\Payments\Models\InvoiceDraft;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * The api surface's mirror of App\Payments\Http\Controllers\InvoiceController:
 * the same rules, the same IssueInvoice, `scope:` in place of the staff
 * session. A staff token still has to reach the project an invoice names
 * (ClientCasePolicy::managePaymentPlan); a machine token has no crew to narrow it.
 */
class InvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Invoice::query()
            ->when($request->user('api') instanceof User, fn (Builder $q) => $q->where(fn (Builder $q) => $q->whereNull('case_id')
                ->orWhereHas('case', fn (Builder $case) => $case->visibleTo($request->user('api')))))
            ->with(['installments', 'creditNotes', 'contact', 'case', 'views']);

        return CrmReadController::page($query, $request, InvoiceRowData::class);
    }

    public function show(Invoice $invoice): JsonResponse
    {
        return response()->json(['data' => InvoiceDetailData::fromModel($invoice)]);
    }

    public function store(Request $request, IssueInvoice $issue, CreateContact $createContact): JsonResponse
    {
        $validated = $request->validate(self::issueRules());

        return $this->issue($request, $validated, $issue, $createContact);
    }

    public function issueDraft(Request $request, InvoiceDraft $draft, IssueInvoice $issue, CreateContact $createContact): JsonResponse
    {
        $validated = Validator::make($draft->payload, self::issueRules())->validate();

        $response = $this->issue($request, $validated, $issue, $createContact);
        $draft->delete();

        return $response;
    }

    /** @return array<string, list<mixed>> */
    public static function issueRules(): array
    {
        $rules = AdminInvoiceController::rules();

        return [
            ...$rules,
            'issued_on' => [...$rules['issued_on'], AdminInvoiceController::notBeforeLatestInvoice(...)],
            'contact.name' => ['required_without_all:contact_id,case_id', 'nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.title' => ['required', 'string', 'max:255'],
        ];
    }

    /** @param  array<string, mixed>  $validated */
    public static function project(Request $request, array $validated): ?ClientCase
    {
        if (! isset($validated['case_id'])) {
            return null;
        }

        $project = ClientCase::query()->where('public_id', $validated['case_id'])->firstOrFail();
        $user = $request->user('api');

        abort_if($user instanceof User && ! $user->can('managePaymentPlan', $project), 403);

        return $project;
    }

    /** @param  array<string, mixed>  $validated */
    private function issue(Request $request, array $validated, IssueInvoice $issue, CreateContact $createContact): JsonResponse
    {
        $project = self::project($request, $validated);
        $contact = match (true) {
            isset($validated['contact_id']) => Contact::query()->where('public_id', $validated['contact_id'])->firstOrFail(),
            isset($validated['contact']['name']) => $createContact(array_filter($validated['contact'], fn ($value) => $value !== null)),
            default => $project?->primaryContact(),
        };

        $invoice = $issue->positions(
            $contact,
            $project,
            AdminInvoiceController::positions($validated, strict: true),
            $validated['due_on'] ?? null,
            [
                'issued_on' => $validated['issued_on'] ?? null,
                'intro' => InlineHtmlSanitizer::letter($validated['intro'] ?? null),
                'outro' => InlineHtmlSanitizer::letter($validated['outro'] ?? null),
                'discount_type' => $validated['discount_type'] ?? null,
                'discount_value' => $validated['discount_value'] ?? null,
            ],
            $request->user('api') instanceof User ? $request->user('api') : null,
        );

        return response()->json(['data' => InvoiceDetailData::fromModel($invoice->fresh())], 201);
    }
}
