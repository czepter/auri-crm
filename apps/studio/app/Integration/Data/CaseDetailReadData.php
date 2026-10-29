<?php

namespace App\Integration\Data;

use App\Crm\Models\ClientCase;
use App\Crm\Models\Shoot;
use App\Quotes\Data\QuoteData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What GET /projects/{project} answers with, and nothing else does:
 * CaseReadData plus what a caller would otherwise have to page three more
 * endpoints for. The index keeps CaseReadData — a contact join on every
 * row is the wrong price for reading one.
 *
 * Internal notes stay out: they hang off ManageNotes, and a token holding
 * ViewAllCases must not reach them through a project.
 */
#[TypeScript]
class CaseDetailReadData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $type,
        public ?string $typeOther,
        public bool $portalVisible,
        public object $fields,
        public string $updatedAt,
        public ?CaseLeadReadData $lead,
        public ?string $bookedAt,
        public ?string $bookedVia,
        public ?string $closedAt,
        public ?string $closeOutcome,
        public ?string $closeReason,
        public int $openTaskCount,
        /** @var list<CaseContactReadData> */
        public array $contacts,
        /** @var list<ShootReadData> */
        public array $shoots,
        /** @var list<QuoteData> */
        public array $quotes,
    ) {}

    public static function fromModel(ClientCase $project): self
    {
        $project->loadMissing(['lead', 'contacts', 'shoots.crew.user', 'quotes.items', 'quotes.paymentStages', 'quotes.predecessor']);
        $project->loadCount(['tasks as open_task_count' => fn ($query) => $query->whereNull('completed_at')]);

        return new self(
            id: $project->public_id,
            name: $project->name,
            type: $project->type->key,
            typeOther: $project->type_other,
            portalVisible: $project->portal_visible,
            fields: (object) ($project->field_values ?? []),
            updatedAt: $project->updated_at->toIso8601String(),
            lead: $project->lead === null ? null : CaseLeadReadData::fromModel($project->lead),
            bookedAt: $project->booked_at?->toIso8601String(),
            bookedVia: $project->booked_via?->value,
            closedAt: $project->closed_at?->toIso8601String(),
            closeOutcome: $project->close_outcome?->value,
            closeReason: $project->close_reason,
            openTaskCount: (int) $project->open_task_count,
            contacts: $project->contacts->map(CaseContactReadData::fromModel(...))->all(),
            shoots: $project->shoots
                ->map(fn (Shoot $shoot) => ShootReadData::fromModel($shoot->setRelation('case', $project)))
                ->all(),
            quotes: $project->quotes->map(QuoteData::fromModel(...))->all(),
        );
    }
}
