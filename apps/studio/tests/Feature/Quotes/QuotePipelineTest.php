<?php

use App\Crm\Models\ClientCase;
use App\Models\User;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Quotes\Support\QuotePipeline;
use App\Support\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('reads what was in play a year ago from the audit trail', function () {
    $staff = User::factory()->withMfa()->create();
    $project = ClientCase::factory()->create();

    $quote = fn (int $total, QuoteStatus $status) => Quote::factory()->for($project, 'case')->create(['total' => $total, 'status' => $status]);

    $this->travelTo(now()->subMonths(14));
    $acceptedLater = $quote(300_000, QuoteStatus::Accepted);
    $acceptedBefore = $quote(200_000, QuoteStatus::Accepted);
    $revisedBefore = $quote(100_000, QuoteStatus::Superseded);
    foreach ([$acceptedLater, $acceptedBefore, $revisedBefore] as $created) {
        AuditLogger::record(event: 'quote.created', actor: $staff, subject: $created->public_id, projectId: $project->id);
    }

    $this->travelBack();
    $this->travelTo(now()->subMonths(13));
    AuditLogger::record(event: 'quote.accepted', subject: $acceptedBefore->public_id, projectId: $project->id);
    AuditLogger::record(event: 'quote.revised', actor: $staff, context: ['predecessor' => $revisedBefore->public_id], subject: 'q-revision', projectId: $project->id);

    $this->travelBack();
    $this->travelTo(now()->subMonths(6));
    AuditLogger::record(event: 'quote.accepted', subject: $acceptedLater->public_id, projectId: $project->id);
    $quote(900_000, QuoteStatus::Sent);

    $this->travelBack();

    expect(QuotePipeline::asOf(now()->subYear(), ClientCase::query()->select('id')))->toBe(300_000);
});
