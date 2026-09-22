<?php

use App\Crm\Enums\LeadSource;
use App\Crm\Models\Contact;
use App\Crm\Models\Job;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use App\Enums\StaffRole;
use App\Gallery\Actions\ReadAfterDeliveryColumn;
use App\Gallery\Models\Gallery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create();
    $this->actingAs($this->staff, 'staff');
});

it('rows a booked project whose lead names a referrer', function () {
    $referrer = Contact::factory()->create(['name' => 'Familie Osterhoff', 'phone' => '+49 40 999']);
    $project = Project::factory()->create(['name' => 'Steffens & Falk']);
    Job::factory()->for($project)->create();
    Lead::factory()->for($project)->create([
        'source' => LeadSource::Referral,
        'referred_by_contact_id' => $referrer->id,
    ]);

    $rows = app(ReadAfterDeliveryColumn::class)();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->who)->toBe('Familie Osterhoff')
        ->and($rows->first()->what)->toContain('Steffens & Falk')
        ->and($rows->first()->action->label)->toBe('Bedanken')
        ->and($rows->first()->action->kind)->toBe('tel');
});

it('does not row a referral whose project was never booked', function () {
    // Nothing to thank anyone for yet.
    $referrer = Contact::factory()->create();
    $project = Project::factory()->create();
    Lead::factory()->for($project)->create([
        'source' => LeadSource::Referral,
        'referred_by_contact_id' => $referrer->id,
    ]);

    expect(app(ReadAfterDeliveryColumn::class)())->toBeEmpty();
});

it('does not row a referral nobody wrote a referrer down for', function () {
    $project = Project::factory()->create();
    Job::factory()->for($project)->create();
    Lead::factory()->for($project)->create([
        'source' => LeadSource::Referral,
        'referred_by_contact_id' => null,
    ]);

    expect(app(ReadAfterDeliveryColumn::class)())->toBeEmpty();
});

it('rows a gallery running out, pointing at the gallery', function () {
    $project = Project::factory()->create(['name' => "Hensel Hochzeit '25"]);
    $gallery = Gallery::factory()->for($project)->create([
        'expires_at' => now()->addDays(12),
    ]);

    $rows = app(ReadAfterDeliveryColumn::class)();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->what)->toContain('Galerie läuft')
        ->and($rows->first()->action->label)->toBe('Album anbieten')
        ->and($rows->first()->action->kind)->toBe('link')
        ->and($rows->first()->action->target)->toBe("/galerien/{$gallery->public_id}");
});

it('does not row a gallery kept indefinitely', function () {
    Gallery::factory()->create(['expires_at' => null]);

    expect(app(ReadAfterDeliveryColumn::class)())->toBeEmpty();
});

it('does not row a gallery whose expiry is far off', function () {
    Gallery::factory()->create(['expires_at' => now()->addDays(200)]);

    expect(app(ReadAfterDeliveryColumn::class)())->toBeEmpty();
});

it('shows a crew member only their own projects', function () {
    $project = Project::factory()->create();
    Gallery::factory()->for($project)->create(['expires_at' => now()->addDays(12)]);

    $crew = User::factory()->withMfa()->create(['role' => StaffRole::Contributor]);

    expect(app(ReadAfterDeliveryColumn::class)($crew))->toBeEmpty();
});
