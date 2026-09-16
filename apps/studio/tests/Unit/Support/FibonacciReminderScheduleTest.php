<?php

use App\Support\FibonacciReminderSchedule;
use Carbon\CarbonImmutable;

$anchor = fn () => CarbonImmutable::parse('2026-03-01 10:00');

it('reaches no rung before the third day', function () use ($anchor) {
    expect(FibonacciReminderSchedule::reachedRungs($anchor(), CarbonImmutable::parse('2026-03-03 09:00')))->toBe(0);
});

it('climbs one rung per fibonacci day', function () use ($anchor) {
    $rungs = collect([3, 5, 8, 13, 21])
        ->map(fn (int $day) => FibonacciReminderSchedule::reachedRungs($anchor(), $anchor()->addDays($day)))
        ->all();

    expect($rungs)->toBe([1, 2, 3, 4, 5]);
});

// A scheduler that stood a week sends once, not once per missed run.
it('collapses missed runs into a single due reminder', function () use ($anchor) {
    expect(FibonacciReminderSchedule::reachedRungs($anchor(), $anchor()->addDays(9)))->toBe(3)
        ->and(FibonacciReminderSchedule::isDue($anchor(), 1, $anchor()->addDays(9)))->toBeTrue()
        ->and(FibonacciReminderSchedule::isDue($anchor(), 3, $anchor()->addDays(9)))->toBeFalse();
});

it('stops at the ceiling', function () use ($anchor) {
    expect(FibonacciReminderSchedule::isDue($anchor(), 7, $anchor()->addDays(90)))->toBeTrue()
        ->and(FibonacciReminderSchedule::isDue($anchor(), 7, $anchor()->addDays(90), ceilingDays: 80))->toBeFalse();
});

it('reaches no rung for a date still ahead', function () use ($anchor) {
    expect(FibonacciReminderSchedule::reachedRungs($anchor(), $anchor()->subDays(4)))->toBe(0);
});
