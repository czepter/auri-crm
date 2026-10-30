<?php

use App\Automation\Enums\AutomationAction;
use App\Automation\Enums\AutomationCondition;
use App\Automation\Enums\AutomationRecipient;
use App\Automation\Models\AutomationRule;
use App\Crm\Events\CaseBooked;
use App\Enums\StaffRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists the rules and edits one', function () {
    $owner = staffMember(StaffRole::Admin);
    $rule = AutomationRule::factory()->create(['name' => 'Shooting-Erinnerung']);

    $this->actingAs($owner, 'staff')
        ->get('/einstellungen/automationen')
        ->assertInertia(fn ($page) => $page->component('Settings/Automations')->has('rules', 1));

    $this->actingAs($owner, 'staff')
        ->patch("/einstellungen/automationen/{$rule->public_id}", [
            'name' => 'Crew — Tag vorher',
            'anchor' => 'per_shoot',
            'offset_days' => 1,
            'direction' => 'before',
            'recipient_type' => 'crew',
            'action' => 'task',
            'condition' => 'payment_open',
            'notification_subject' => 'Morgen: {{ project.name }}',
            'notification_body' => 'Ausrüstung packen.',
            'is_active' => true,
        ])
        ->assertRedirect();

    expect($rule->fresh()->recipient_type)->toBe(AutomationRecipient::Crew)
        ->and($rule->fresh()->action)->toBe(AutomationAction::Task)
        ->and($rule->fresh()->condition)->toBe(AutomationCondition::PaymentOpen);
});

// Eine Ereignisregel traegt weder Anker noch Abstand — AutomationRule::booted()
// weist eine zurueck, die beides hat.
it('saves an event rule, which carries no anchor', function () {
    $rule = AutomationRule::factory()->event(CaseBooked::class)->create();

    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->patch("/einstellungen/automationen/{$rule->public_id}", [
            'name' => 'Buchungsbestätigung',
            'recipient_type' => 'client',
            'condition' => null,
            'notification_subject' => 'Ihre Buchung ist bestätigt',
            'notification_body' => 'Wir freuen uns.',
            'is_active' => true,
        ])
        ->assertRedirect();

    expect($rule->fresh()->notification_body)->toBe('Wir freuen uns.')
        ->and($rule->fresh()->anchor)->toBeNull();
});

// AuthorizesStaffSettings antwortet mit 404, nicht 403: eine Flaeche, die
// diese Rolle nicht hat, existiert fuer sie nicht.
it('keeps the screen away from a role that may not manage settings', function () {
    $this->actingAs(staffMember(StaffRole::Manager), 'staff')
        ->get('/einstellungen/automationen')
        ->assertNotFound();
});
