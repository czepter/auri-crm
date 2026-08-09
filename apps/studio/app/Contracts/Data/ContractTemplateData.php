<?php

namespace App\Contracts\Data;

use App\Contracts\Models\ContractTemplate;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ContractTemplateData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $body,
        public int $version,
    ) {}

    public static function fromModel(ContractTemplate $template): self
    {
        return new self(
            id: $template->public_id,
            name: $template->name,
            body: $template->body,
            version: $template->version,
        );
    }
}
