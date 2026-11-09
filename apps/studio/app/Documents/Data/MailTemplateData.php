<?php

namespace App\Documents\Data;

use App\Documents\Enums\DocumentTemplateType;
use App\Documents\Models\DocumentTemplate;
use App\Documents\Support\MergeFieldResolver;
use App\Documents\Support\MergeFieldVocabulary;
use App\Documents\Support\PlainTextBody;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * E-10: one mail template, already resolved against the project the composer
 * is standing in.
 *
 * Resolved on the server rather than sent as a body with placeholders in it,
 * because the resolved text is what the staff member edits and what gets
 * stored — a placeholder that reached the composer would have to be resolved
 * again by something downstream, and E-06's `messages.body` has no second
 * pass. What the composer shows is what the client receives.
 *
 * `unknownFields` names the paths this template references that the
 * vocabulary does not have. MergeFieldResolver would blank them silently,
 * which on a client mail means a sentence quietly losing its subject, so a
 * template carrying one is offered as unusable with the field named rather
 * than offered as fine.
 */
#[TypeScript]
class MailTemplateData extends Data
{
    /** @param list<string> $unknownFields */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $subject,
        public string $body,
        public array $unknownFields,
    ) {}

    /** @param  array<string, mixed>  $mergeData */
    public static function fromModel(
        DocumentTemplate $template,
        array $mergeData,
    ): self {
        return new self(
            id: $template->public_id,
            name: $template->name,
            subject: filled($template->subject) ? MergeFieldResolver::resolve($template->subject, $mergeData) : null,
            body: PlainTextBody::fromHtml(
                MergeFieldResolver::resolve($template->body, $mergeData),
            ),
            unknownFields: MergeFieldVocabulary::unknownPathsIn($template->body),
        );
    }

    /**
     * The mail templates fit for a case, resolved against $mergeData and
     * without those that name a field the vocabulary lacks.
     *
     * @param  array<string, mixed>  $mergeData
     * @return list<self>
     */
    public static function forCompose(?string $shootKind, array $mergeData): array
    {
        return DocumentTemplate::query()
            ->current()
            ->ofType(DocumentTemplateType::Mail)
            ->forShootKind($shootKind)
            ->orderBy('name')
            ->get()
            ->map(fn (DocumentTemplate $template) => self::fromModel($template, $mergeData))
            ->filter(fn (self $template) => $template->unknownFields === [])
            ->values()
            ->all();
    }
}
