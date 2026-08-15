<?php

use App\Automation\Actions\FireAutomationRule;
use App\Automation\Enums\AutomationAnchor;
use App\Automation\Enums\AutomationOffsetDirection;
use App\Automation\Enums\AutomationTriggerType;
use App\Automation\Models\AutomationRule;
use App\Contracts\Enums\ContractStatus;
use App\Contracts\Models\Contract;
use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use App\Enums\StaffRole;
use App\Models\User;
use App\Quotes\Actions\ReadInquiryColumn;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create();
    $this->actingAs($this->staff, 'staff');

    $this->rule = AutomationRule::factory()->create([
        'key' => 'quote-follow-up',
        'trigger_type' => AutomationTriggerType::DateOffset,
        'anchor' => AutomationAnchor::QuoteValidity,
        'offset_days' => 7,
        'direction' => AutomationOffsetDirection::Before,
    ]);
});

function projectWithPhone(string $name, string $phone = '+49 40 1234567'): Project
{
    $project = Project::factory()->create(['name' => $name]);
    $project->attachContact(
        Contact::factory()->create(['phone' => $phone]),
        ProjectContactRole::Primary,
    );

    return $project;
}

it('does not row a silent quote the machine has not chased yet', function () {
    // Decision 2, and the whole design: the Übersicht shows what the machine
    // cannot do. A quote whose reminder has not gone out yet is the machine's
    // job, not a phone call.
    $project = projectWithPhone('Reuter & Sölter');
    Quote::factory()->for($project)->create([
        'status' => QuoteStatus::Sent,
        'valid_until' => now()->addDays(5)->toDateString(),
    ]);

    expect(app(ReadInquiryColumn::class)())->toBeEmpty();
});

it('rows a silent quote once the reminders are exhausted', function () {
    $project = projectWithPhone('Reuter & Sölter');
    $quote = Quote::factory()->for($project)->create([
        'status' => QuoteStatus::Sent,
        'valid_until' => now()->addDays(5)->toDateString(),
    ]);

    app(FireAutomationRule::class)($this->rule, $project, now()->subDays(9)->toDateString());
    app(FireAutomationRule::class)($this->rule, $project, now()->subDays(2)->toDateString());

    $rows = app(ReadInquiryColumn::class)();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->who)->toBe('Reuter & Sölter')
        ->and($rows->first()->what)->toBe('Angebot still,')
        ->and($rows->first()->emphasis)->toBe('läuft '.$quote->valid_until->format('d.m.').' ab')
        ->and($rows->first()->whatTail)->toContain('2× erinnert')
        ->and($rows->first()->action->kind)->toBe('tel')
        ->and($rows->first()->action->target)->toBe('tel:+49401234567')
        ->and($rows->first()->hot)->toBeTrue()
        ->and($rows->first()->projectId)->toBe($project->public_id);
});

it('rows an accepted quote with no contract, pointing at the template choice', function () {
    // contracts.store needs a template. The dashboard has no standing to pick
    // one, so the button opens the screen where the studio does.
    $project = projectWithPhone('Duhnkrack & W.');
    Quote::factory()->for($project)->create([
        'status' => QuoteStatus::Accepted,
        'accepted_at' => now()->subDays(3),
    ]);

    $rows = app(ReadInquiryColumn::class)();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->emphasis)->toBe('Vertrag fehlt')
        ->and($rows->first()->whatTail)->toBeNull()
        ->and($rows->first()->action->kind)->toBe('link')
        ->and($rows->first()->action->target)->toBe("/auftraege/{$project->public_id}/vertrag");
});

it('does not row an accepted quote once its contract exists', function () {
    // A brand-new contract has not earned the unsigned row either
    // (UNSIGNED_AFTER_DAYS = 4), so the accepted-quote row drops off with
    // nothing yet replacing it — this is the one moment the column is
    // legitimately silent about a project that used to be on it.
    $project = projectWithPhone('Duhnkrack & W.');
    Quote::factory()->for($project)->create([
        'status' => QuoteStatus::Accepted,
        'accepted_at' => now()->subDays(3),
    ]);
    Contract::factory()->for($project)->create(['status' => ContractStatus::Rendered]);

    expect(app(ReadInquiryColumn::class)())->toBeEmpty();
});

it('rows a rendered contract left unsigned', function () {
    $project = projectWithPhone('Ahlers & Sobotta');
    Contract::factory()->for($project)->create([
        'status' => ContractStatus::Rendered,
        'created_at' => now()->subDays(6),
    ]);

    $rows = app(ReadInquiryColumn::class)();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->emphasis)->toContain('unsigniert')
        ->and($rows->first()->hot)->toBeFalse();
});

it('drops the action when the contact has no phone number', function () {
    // A button that dials nothing is worse than no button; the row still
    // states the fact.
    $project = projectWithPhone('Ohne Telefon', phone: '');
    $quote = Quote::factory()->for($project)->create([
        'status' => QuoteStatus::Sent,
        'valid_until' => now()->addDays(5)->toDateString(),
    ]);
    app(FireAutomationRule::class)($this->rule, $project, now()->subDay()->toDateString());

    expect(app(ReadInquiryColumn::class)()->first()->action)->toBeNull();
});

it('shows a crew member only their own projects', function () {
    $project = projectWithPhone('Nicht meins');
    Quote::factory()->for($project)->create([
        'status' => QuoteStatus::Accepted,
        'accepted_at' => now()->subDay(),
    ]);

    $crew = User::factory()->withMfa()->create(['role' => StaffRole::SecondShooter]);

    expect(app(ReadInquiryColumn::class)($crew))->toBeEmpty();
});
