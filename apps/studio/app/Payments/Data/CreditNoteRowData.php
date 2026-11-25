<?php

namespace App\Payments\Data;

use App\Payments\Models\CreditNote;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** #409: one Gutschrift as the API lists it. */
#[TypeScript]
class CreditNoteRowData extends Data
{
    public function __construct(
        public string $id,
        public string $number,
        public string $issuedOn,
        public int $amount,
        public ?string $reason,
        public string $invoiceId,
        public string $invoiceNumber,
        public ?string $projectId,
        public ?string $projectName,
        public ?string $contact,
        public bool $hasDocument,
    ) {}

    public static function fromModel(CreditNote $note): self
    {
        return new self(
            id: $note->public_id,
            number: $note->number,
            issuedOn: $note->issued_at->format('Y-m-d'),
            amount: (int) $note->amount,
            reason: $note->reason,
            invoiceId: $note->invoice->public_id,
            invoiceNumber: $note->invoice->number,
            projectId: $note->invoice->case?->public_id,
            projectName: $note->invoice->case?->name,
            contact: $note->invoice->contact?->name,
            hasDocument: $note->pdf_path !== null,
        );
    }
}
