<?php

use App\Automation\Models\AutomationRule;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates one AutomationRule per config/automation.php entry', function () {
    $this->artisan('automation:seed-default-rules')->assertSuccessful();

    $keys = collect(config('automation.rules'))->pluck('key');

    expect(AutomationRule::query()->count())->toBe($keys->count());

    foreach ($keys as $key) {
        expect(AutomationRule::query()->where('key', $key)->exists())->toBeTrue();
    }
});

it('does not duplicate rows when run twice', function () {
    $this->artisan('automation:seed-default-rules')->assertSuccessful();
    $this->artisan('automation:seed-default-rules')->assertSuccessful();

    $keys = collect(config('automation.rules'))->pluck('key');

    expect(AutomationRule::query()->count())->toBe($keys->count());
});

it('preserves a tenant edit to a seeded rule on re-seed', function () {
    $this->artisan('automation:seed-default-rules')->assertSuccessful();

    $rule = AutomationRule::query()->where('key', 'shoot-reminder-7-days-before')->firstOrFail();
    $rule->update([
        'notification_subject' => 'Custom subject',
        'notification_body' => 'Custom body the studio wrote themselves.',
        'is_active' => false,
    ]);

    $this->artisan('automation:seed-default-rules')->assertSuccessful();

    $rule->refresh();

    expect($rule->notification_subject)->toBe('Custom subject')
        ->and($rule->notification_body)->toBe('Custom body the studio wrote themselves.')
        ->and($rule->is_active)->toBeFalse();
});
