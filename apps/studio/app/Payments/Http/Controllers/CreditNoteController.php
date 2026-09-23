<?php

namespace App\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Payments\Data\CreditNoteRowData;
use App\Payments\Models\CreditNote;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * #409: the Gutschriften of the whole book. Until this screen they existed
 * only as rows under the invoice that carried them, which answers "what was
 * credited on this invoice" and never "what did we credit this year" — the
 * question the Steuerberater asks.
 *
 * Same authority as the invoice list: a Gutschrift is an invoice document.
 */
class CreditNoteController extends Controller
{
    public function __invoke(Request $request): Response
    {
        InvoiceController::authorizeProject(null);

        $viewer = $request->user('staff');

        $notes = CreditNote::query()
            ->whereHas('invoice', fn ($invoice) => $invoice
                ->where(fn ($query) => $query->whereNull('case_id')
                    ->orWhereHas('case', fn ($project) => $project->visibleTo($viewer))))
            ->with(['invoice.case', 'invoice.contact'])
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('CreditNotes/Index', [
            'creditNotes' => CreditNoteRowData::collect($notes->map(CreditNoteRowData::fromModel(...))),
        ]);
    }
}
