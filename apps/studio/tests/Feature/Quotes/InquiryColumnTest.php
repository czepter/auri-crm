<?php

use App\Automation\Actions\FireAutomationRule;
use App\Automation\Enums\AutomationAnchor;
use App\Automation\Enums\AutomationOffsetDirection;
use App\Automation\Enums\AutomationTriggerType;
use App\Automation\Models\AutomationRule;
use App\Crm\Enums\CaseContactRole;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
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

function projectWithPhone(string $name, string $phone = '+49 40 1234567'): ClientCase
{
    $project = ClientCase::factory()->create(['name' => $name]);
    $project->attachContact(
        Contact::factory()->create(['phone' => $phone]),
        CaseContactRole::Primary,
    );

    return $project;
}

it('does not row a silent quote the machine has not chased yet', function () {
    // Decision 2, and the whole design: the Übersicht shows what the machine
    // cannot do. A quote whose reminder has not gone out yet is the machine's
    // job, not a phone call.
    $project = projectWithPhone('Reuter & Sölter');
    Quote::factory()->for($project, 'case')->create([
        'status' => QuoteStatus::Sent,
        'valid_until' => now()->addDays(5)->toDateString(),
    ]);

    expect(app(ReadInquiryColumn::class)())->toBeEmpty();
});

it('rows a silent quote once the reminders are exhausted', function () {
    $project = projectWithPhone('Reuter & Sölter');
    $quote = Quote::factory()->for($project, 'case')->create([
        'status' => QuoteStatus::Sent,
        'valid_until' => now()->addDays(5)->toDateString(),
    ]);

    app(FireAutomationRule::class)($this->rule, $project, now()->subDays(9)->toDateString());
    app(FireAutomationRule::class)($this->rule, $project, now()->subDays(2)->toDateString());

    $rows = app(ReadInquiryColumn::class)();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->who)->toBe('Reuter & Sölter')
        ->and($rows->first()->what)->toBe('Angebot still,')
        ->and($rows->first()->emphasis)->toBe('läuft '.$quote->valid_until->format('d.m.Y').' ab')
        ->and($rows->first()->whatTail)->toContain('2× erinnert')
        ->and($rows->first()->action->kind)->toBe('tel')
        ->and($rows->first()->action->target)->toBe('tel:+49401234567')
        ->and($rows->first()->hot)->toBeTrue()
        ->and($rows->first()->projectId)->toBe($project->public_id);
});

it('drops the action when the contact has no phone number', function () {
    // A button that dials nothing is worse than no button; the row still
    // states the fact.
    $project = projectWithPhone('Ohne Telefon', phone: '');
    $quote = Quote::factory()->for($project, 'case')->create([
        'status' => QuoteStatus::Sent,
        'valid_until' => now()->addDays(5)->toDateString(),
    ]);
    app(FireAutomationRule::class)($this->rule, $project, now()->subDay()->toDateString());

    expect(app(ReadInquiryColumn::class)()->first()->action)->toBeNull();
});

it('shows a crew member only their own projects', function () {
    $project = projectWithPhone('Nicht meins');
    Quote::factory()->for($project, 'case')->create([
        'status' => QuoteStatus::Accepted,
        'accepted_at' => now()->subDay(),
    ]);

    $crew = User::factory()->withMfa()->create(['role' => StaffRole::Contributor]);

    expect(app(ReadInquiryColumn::class)($crew))->toBeEmpty();
});

it('orders chased quotes by soonest expiry first', function () {
    $soon = projectWithPhone('Läuft bald ab');
    Quote::factory()->for($soon, 'case')->create(['status' => QuoteStatus::Sent, 'valid_until' => now()->addDays(3)->toDateString()]);
    app(FireAutomationRule::class)($this->rule, $soon, now()->subDay()->toDateString());

    $later = projectWithPhone('Läuft später ab');
    Quote::factory()->for($later, 'case')->create(['status' => QuoteStatus::Sent, 'valid_until' => now()->addDays(10)->toDateString()]);
    app(FireAutomationRule::class)($this->rule, $later, now()->subDay()->toDateString());

    $rows = app(ReadInquiryColumn::class)();

    expect($rows->pluck('who')->all())->toBe(['Läuft bald ab', 'Läuft später ab']);
});

it('does not throw when no viewer is authenticated', function () {
    // ClientCase::visibleTo() takes a non-nullable User; the guard on each
    // sub-query keeps a null viewer from ever reaching it (mirrors
    // ReadPaymentColumn's shape).
    auth('staff')->logout();

    Quote::factory()->create(['status' => QuoteStatus::Accepted, 'accepted_at' => now()->subDay()]);

    expect(fn () => app(ReadInquiryColumn::class)())->not->toThrow(TypeError::class);
});
