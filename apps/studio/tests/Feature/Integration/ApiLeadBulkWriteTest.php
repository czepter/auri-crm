<?php

use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use App\Enums\Capability;
use App\Integration\Models\ApiToken;
use App\Models\User;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    useSurface(Surface::API);
    Notification::fake();
});

function bulkRow(string $name): array
{
    return [
        'contact_name' => $name,
        'contact_email' => str($name)->slug().'@example.test',
        'project_name' => $name.' — Portrait',
        'type' => 'portrait',
        'source' => 'website',
        'captured_via' => 'other',
    ];
}

function postBulk(array $leads, array $headers = [])
{
    $token = ApiToken::factory()->withScopes(Capability::ManageLeads)->create();

    return test()
        ->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'bulk-'.count($leads).'-'.md5(json_encode([$leads, $headers])))
        ->withHeaders($headers)
        ->postJson('/api/leads/bulk', ['leads' => $leads]);
}

it('captures every row in one call', function () {
    $response = postBulk([bulkRow('Nina Voss'), bulkRow('Timo Reiser')]);

    $response->assertCreated()->assertJson(['captured' => 2]);

    expect(Lead::count())->toBe(2)
        ->and(Project::count())->toBe(2)
        ->and($response->json('leads.0.lead.stage'))->toBe('new');

    expect(integerIdPaths($response->json()))->toBe([]);
});

it('writes nothing at all when one row is unusable', function () {
    User::factory()->create();

    $response = postBulk([bulkRow('Nina Voss'), ['project_name' => 'nameless', 'type' => 'portrait']]);

    $response->assertInvalid(['leads.1.contact_name']);

    expect(Lead::count())->toBe(0)
        ->and(Project::count())->toBe(0);
    Notification::assertNothingSent();
});

it('stays silent for the whole batch when the header asks', function () {
    User::factory()->create();

    postBulk([bulkRow('Nina Voss'), bulkRow('Timo Reiser')], ['X-Silent-Write' => '1'])->assertCreated();

    expect(Lead::count())->toBe(2);
    Notification::assertNothingSent();
});

it('refuses a batch without the manage_leads scope', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageContacts)->create();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'bulk-scope')
        ->postJson('/api/leads/bulk', ['leads' => [bulkRow('Nina Voss')]])
        ->assertForbidden();

    expect(Lead::count())->toBe(0);
});

it('refuses more rows than one batch may carry', function () {
    postBulk(array_map(fn (int $i) => bulkRow("Kundin {$i}"), range(1, 501)))
        ->assertInvalid(['leads']);

    expect(Lead::count())->toBe(0);
});
