<?php

namespace App\Integration\Http\Controllers;

use App\Crm\Models\Contact;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Payments\Http\Controllers\InvoiceController as AdminInvoiceController;
use App\Payments\Models\InvoiceDraft;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** The create form kept as it was posted — see InvoiceDraft; the same payload the admin's /rechnungen/entwuerfe stores. */
class InvoiceDraftController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = InvoiceDraft::query()
            ->when($request->user('api') instanceof User, fn (Builder $q) => $q->where(fn (Builder $q) => $q->whereNull('case_id')
                ->orWhereHas('case', fn (Builder $case) => $case->visibleTo($request->user('api')))))
            ->with(['case', 'contact']);

        return CrmReadController::page($query, $request, self::class);
    }

    public function show(InvoiceDraft $draft): JsonResponse
    {
        return response()->json(['data' => self::fromModel($draft)]);
    }

    public function store(Request $request): JsonResponse
    {
        $draft = InvoiceDraft::create([
            ...$this->attributes($request),
            'created_by' => $request->user('api') instanceof User ? $request->user('api')->id : null,
        ]);

        return response()->json(['data' => self::fromModel($draft)], 201);
    }

    public function update(Request $request, InvoiceDraft $draft): JsonResponse
    {
        $draft->update($this->attributes($request));

        return response()->json(['data' => self::fromModel($draft)]);
    }

    public function destroy(InvoiceDraft $draft): Response
    {
        $draft->delete();

        return response()->noContent();
    }

    /** @return array{payload: array<string, mixed>, case_id: ?int, contact_id: ?int} */
    private function attributes(Request $request): array
    {
        $validated = $request->validate(AdminInvoiceController::rules());

        return [
            'payload' => $validated,
            'case_id' => InvoiceController::project($request, $validated)?->id,
            'contact_id' => isset($validated['contact_id'])
                ? Contact::query()->where('public_id', $validated['contact_id'])->firstOrFail()->id
                : null,
        ];
    }

    /** @return array<string, mixed> */
    public static function fromModel(InvoiceDraft $draft): array
    {
        return [
            'id' => $draft->public_id,
            'projectId' => $draft->case?->public_id,
            'contactId' => $draft->contact?->public_id,
            'updatedAt' => $draft->updated_at->toIso8601String(),
            'payload' => $draft->payload,
        ];
    }
}
