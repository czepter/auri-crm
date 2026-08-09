<?php

use App\Automation\Enums\AutomationAnchor;
use App\Automation\Enums\AutomationOffsetDirection;
use App\Automation\Enums\AutomationTriggerType;
use App\Automation\Models\AutomationRule;
use App\Crm\Events\ProjectBooked;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('refuses a date-offset rule missing its anchor, offset or direction', function () {
    AutomationRule::factory()->make([
        'trigger_type' => AutomationTriggerType::DateOffset,
        'anchor' => null,
        'offset_days' => null,
        'direction' => null,
    ])->save();
})->throws(InvalidArgumentException::class);

it('refuses an event rule missing its event_class', function () {
    AutomationRule::factory()->make([
        'trigger_type' => AutomationTriggerType::Event,
        'event_class' => null,
    ])->save();
})->throws(InvalidArgumentException::class);

it('clears the date-offset columns when saving an event rule', function () {
    $rule = AutomationRule::factory()
        ->dateOffset(AutomationAnchor::PerShoot, 7, AutomationOffsetDirection::Before)
        ->create();

    $rule->update([
        'trigger_type' => AutomationTriggerType::Event,
        'event_class' => ProjectBooked::class,
    ]);

    expect($rule->fresh())
        ->anchor->toBeNull()
        ->offset_days->toBeNull()
        ->direction->toBeNull();
});
