<?php

namespace App\Documents\Data;

use App\Crm\Models\Project;
use App\Documents\Models\DocumentTemplate;
use App\Documents\Support\DocumentMergeDataBuilder;
use App\Documents\Support\MergeFieldResolver;
use App\Documents\Support\MergeFieldVocabulary;
use App\Documents\Support\PlainTextBody;
use App\Settings\Support\StudioSettingsResolver;
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
        public string $body,
        public array $unknownFields,
    ) {}

    public static function fromModel(
        DocumentTemplate $template,
        Project $project,
        StudioSettingsResolver $settings,
        DocumentMergeDataBuilder $mergeData,
    ): self {
        return new self(
            id: $template->public_id,
            name: $template->name,
            body: PlainTextBody::fromHtml(
                MergeFieldResolver::resolve($template->body, $mergeData->fromProject($project, $settings)),
            ),
            unknownFields: MergeFieldVocabulary::unknownPathsIn($template->body),
        );
    }
}
