<?php

namespace App\Finance\Data;

use App\Finance\Models\FinanceDocument;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class FinanceDocumentData extends Data
{
    public function __construct(
        public string $id,
        public string $filename,
        public string $mime,
        public ?int $size,
        public bool $isSelfIssued,
        public string $url,
    ) {}

    public static function fromModel(FinanceDocument $document): self
    {
        return new self(
            $document->public_id,
            $document->filename,
            $document->mime,
            $document->size,
            $document->is_self_issued,
            route('finance.documents.show', $document, absolute: false),
        );
    }
}
