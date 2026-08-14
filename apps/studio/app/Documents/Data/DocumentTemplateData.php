<?php

namespace App\Documents\Data;

use App\Crm\Enums\ShootKind;
use App\Documents\Models\DocumentTemplate;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class DocumentTemplateData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $body,
        /** @var array<string, mixed>|null */
        public ?array $blocks,
        public int $version,
        public string $type,
        public string $typeLabel,
        public ?string $shootKind,
        public ?string $shootKindLabel,
    ) {}

    public static function fromModel(DocumentTemplate $template): self
    {
        return new self(
            id: $template->public_id,
            name: $template->name,
            body: $template->body,
            blocks: $template->blocks,
            version: $template->version,
            type: $template->type->value,
            typeLabel: $template->type->label(),
            shootKind: $template->shoot_kind,
            shootKindLabel: $template->shoot_kind === null ? null : ShootKind::from($template->shoot_kind)->label(),
        );
    }
}
