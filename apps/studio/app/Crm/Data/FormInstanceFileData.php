<?php

namespace App\Crm\Data;

use App\Crm\Models\FormInstanceFile;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class FormInstanceFileData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public int $size,
        public string $uploadedAt,
    ) {}

    public static function fromModel(FormInstanceFile $file): self
    {
        return new self(
            id: $file->public_id,
            name: $file->name,
            size: $file->size,
            uploadedAt: $file->created_at->toIso8601String(),
        );
    }
}
