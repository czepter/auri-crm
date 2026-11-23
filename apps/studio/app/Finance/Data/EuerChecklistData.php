<?php

namespace App\Finance\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class EuerChecklistData extends Data
{
    public function __construct(
        public int $withoutReceipt,
        public int $drafts,
        public int $openInvoices,
        public int $openInvoicesRemaining,
        public int $foreignWithoutEur,
    ) {}

    public function isEmpty(): bool
    {
        return $this->withoutReceipt + $this->drafts + $this->openInvoices + $this->foreignWithoutEur === 0;
    }
}
