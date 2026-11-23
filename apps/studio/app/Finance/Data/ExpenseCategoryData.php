<?php

namespace App\Finance\Data;

use App\Finance\Models\ExpenseCategory;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ExpenseCategoryData extends Data
{
    public function __construct(
        public string $id,
        public string $key,
        public string $label,
        public string $euerLine,
        public ?string $skr04Account,
    ) {}

    public static function fromModel(ExpenseCategory $category): self
    {
        return new self($category->public_id, $category->key, $category->label, $category->euer_line, $category->skr04_account);
    }
}
