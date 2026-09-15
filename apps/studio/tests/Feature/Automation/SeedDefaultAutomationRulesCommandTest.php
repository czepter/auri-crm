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

// A thank-you the day after the wedding reads as a form letter; the studio writes that one itself.
it('ships no automated thank-you after the wedding', function () {
    expect(collect(config('automation.rules'))->pluck('key'))->not->toContain('thank-you-after-wedding');
});

// The offset is editable on the screen; a "7 Tage" in the text goes stale the moment it is.
it('writes no offset into a default rule text', function () {
    $texts = collect(config('automation.rules'))
        ->flatMap(fn (array $rule) => [$rule['notification_subject'], $rule['notification_body']])
        ->flatMap(fn (string|array $text) => (array) $text);

    foreach ($texts as $text) {
        expect($text)->not->toMatch('/\d|\bwoche|\btage?n?\b|\bmorgen\b/iu');
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
