<?php

use App\Crm\Enums\CaseContactRole;
use App\Crm\Enums\ShootStatus;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Crm\Models\Shoot;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => useSurface(Surface::PORTAL));

function inquiryWithQuote(?QuoteStatus $status): array
{
    $contact = Contact::factory()->create();
    $project = ClientCase::factory()->create(['portal_visible' => true]);
    $project->attachContact($contact, CaseContactRole::Primary);
    Shoot::factory()->for($project, 'case')->on('2026-06-14')->create(['is_primary' => true, 'status' => ShootStatus::Requested]);

    if ($status !== null) {
        Quote::factory()->for($project, 'case')->create([
            'status' => $status,
            'sent_at' => now(),
            'valid_until' => '2026-11-10',
            'net' => 350000,
            'vat_mode' => 'standard',
            'vat_rate' => 1900,
            'vat_amount' => 66500,
            'total' => 350000,
        ]);
    }

    $page = test()->actingAs($contact, 'portal')->get("/projekte/{$project->public_id}")->assertOk()->viewData('page');

    return [$project, $page];
}

it('hands the inquiry screen the sent quote to link to', function () {
    [$project, $page] = inquiryWithQuote(QuoteStatus::Sent);

    expect($page['component'])->toBe('Projects/Inquiry')
        ->and($page['props']['project']['quote'])->toMatchArray([
            'href' => "/projekte/{$project->public_id}/angebot",
            'total' => 416500,
            'validUntil' => '2026-11-10',
            'acceptedOn' => null,
        ]);
});

it('hands the inquiry screen no quote while none was sent', function () {
    [, $page] = inquiryWithQuote(null);

    expect($page['props']['project']['quote'])->toBeNull();
});
