<?php

use App\Crm\Enums\DueAnchor;
use App\Crm\Enums\StagePipeline;
use App\Crm\Enums\TaskAudience;
use App\Crm\Models\Lead;
use App\Crm\Models\Task;
use App\Crm\Models\TaskTemplate;
use App\Enums\Capability;
use App\Integration\Models\ApiToken;
use App\Models\User;
use App\Notifications\Studio\InquiryReceived;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    useSurface(Surface::API);
    Notification::fake();
});

function captureLead(ApiToken $token, string $key, array $headers = [])
{
    return test()
        ->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', $key)
        ->withHeaders($headers)
        ->postJson('/api/leads', [
            'contact_name' => 'Nina Voss',
            'contact_email' => 'nina@example.test',
            'project_name' => 'Voss — Portrait',
            'type' => 'portrait',
            'source' => 'website',
            'captured_via' => 'other',
        ]);
}

it('notifies the studio about a lead captured over the api', function () {
    $owner = User::factory()->create();
    $token = ApiToken::factory()->withScopes(Capability::ManageLeads)->create();

    captureLead($token, 'loud')->assertCreated();

    Notification::assertSentTo($owner, InquiryReceived::class);
});

it('says nothing about one captured with X-Silent-Write', function () {
    User::factory()->create();
    $token = ApiToken::factory()->withScopes(Capability::ManageLeads)->create();

    captureLead($token, 'silent', ['X-Silent-Write' => '1'])->assertCreated();

    expect(Lead::count())->toBe(1);
    Notification::assertNothingSent();
});

function contactedStageTemplate(): void
{
    TaskTemplate::create([
        'title' => 'Angebot nachfassen',
        'audience' => TaskAudience::Client,
        'project_type' => null,
        'pipeline' => StagePipeline::Lead,
        'stage' => 'contacted',
        'day_offset' => 2,
        'anchor' => DueAnchor::StageEntry,
    ]);
}

it('applies the stage template to a lead advanced over the api', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageLeads)->create();
    $lead = Lead::factory()->create();
    contactedStageTemplate();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'stage-loud')
        ->postJson("/api/cases/{$lead->case->public_id}/lead/stage", ['stage' => 'contacted'])
        ->assertOk();

    expect(Task::count())->toBe(1);
});

it('creates no follow-up tasks for a silently advanced lead', function () {
    $token = ApiToken::factory()->withScopes(Capability::ManageLeads)->create();
    $lead = Lead::factory()->create();
    contactedStageTemplate();

    $this->withHeader('Authorization', 'Bearer '.bearerToken($token))
        ->withHeader('Idempotency-Key', 'stage-silent')
        ->withHeader('X-Silent-Write', '1')
        ->postJson("/api/cases/{$lead->case->public_id}/lead/stage", ['stage' => 'contacted'])
        ->assertOk();

    expect($lead->fresh()->stage->key)->toBe('contacted')
        ->and(Task::count())->toBe(0);
});
