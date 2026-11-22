<?php

use App\Finance\Enums\DepreciationMethod;
use App\Finance\Support\Depreciation;
use Carbon\CarbonImmutable;

function linearAsset(int $basis, string $acquired, int $life, ?string $disposed = null): Depreciation
{
    return new Depreciation(
        DepreciationMethod::Linear,
        $basis,
        CarbonImmutable::parse($acquired),
        $life,
        $disposed === null ? null : CarbonImmutable::parse($disposed),
    );
}

it('writes a GWG off in the year of acquisition and never again', function () {
    $gwg = new Depreciation(DepreciationMethod::Gwg, 64000, CarbonImmutable::parse('2026-11-30'), null);

    expect($gwg->yearly(2025))->toBe(0)
        ->and($gwg->yearly(2026))->toBe(64000)
        ->and($gwg->yearly(2027))->toBe(0)
        ->and($gwg->bookValue(CarbonImmutable::parse('2026-10-31')))->toBe(64000)
        ->and($gwg->bookValue(CarbonImmutable::parse('2026-11-30')))->toBe(0);
});

it('takes ten months in the first year of a twelve month life acquired in March, and two in the next', function () {
    $asset = linearAsset(120000, '2026-03-15', 12);

    expect($asset->yearly(2026))->toBe(100000)
        ->and($asset->yearly(2027))->toBe(20000)
        ->and($asset->yearly(2028))->toBe(0)
        ->and($asset->bookValue(CarbonImmutable::parse('2026-12-31')))->toBe(20000);
});

it('leaves the remainder to the last of four calendar years of a 36 month life', function () {
    $asset = linearAsset(100000, '2026-11-02', 36);
    expect(array_map($asset->yearly(...), [2026, 2027, 2028, 2029, 2030]))->toBe([5556, 33333, 33333, 27778, 0]);
});

it('adds yearly write-offs and the book value up to the basis for every month and life', function () {
    foreach (range(1, 60) as $life) {
        foreach (range(1, 12) as $month) {
            $asset = linearAsset(99999, sprintf('2026-%02d-10', $month), $life);
            $written = 0;

            foreach (range(2026, 2026 + intdiv($life, 12) + 1) as $year) {
                $written += $asset->yearly($year);
                expect($written + $asset->bookValue(CarbonImmutable::create($year, 12, 31)))->toBe(99999);
            }

            expect($asset->bookValue(CarbonImmutable::create(2026 + intdiv($life, 12) + 1, 12, 31)))->toBe(0);
        }
    }
});

it('stops writing off after the month of a disposal and expenses the rest', function () {
    $asset = linearAsset(36000, '2026-01-20', 36, '2026-06-05');

    expect($asset->yearly(2026))->toBe(6000)
        ->and($asset->yearly(2027))->toBe(0)
        ->and($asset->residualAtDisposal())->toBe(30000)
        ->and($asset->bookValue(CarbonImmutable::parse('2026-05-31')))->toBe(31000)
        ->and($asset->bookValue(CarbonImmutable::parse('2026-12-31')))->toBe(0);
});

it('shares an amount half up', function (int $amount, int $percent, int $expected) {
    expect(Depreciation::share($amount, $percent))->toBe($expected);
})->with([
    'whole' => [5950, 100, 5950],
    'half cent rounds up' => [2975, 50, 1488],
    'none' => [5950, 0, 0],
    'third' => [100, 33, 33],
]);
