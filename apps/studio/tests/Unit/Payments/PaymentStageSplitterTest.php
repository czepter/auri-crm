<?php

use App\Quotes\Support\PaymentStageSplitter;

function paymentStage(int $percent, string $label, int $minimum = 0): object
{
    return (object) ['percent' => $percent, 'label' => $label, 'minimum' => $minimum];
}

// Beide Faelle aus der Spec, §5.5.
it('lets a minimum push the share up and takes the difference off the last stage', function () {
    $stages = collect([
        paymentStage(25, 'Bei der Zusage', minimum: 80_000),
        paymentStage(50, 'Vier Wochen vorher', minimum: 0),
        paymentStage(25, 'Nach der Lieferung', minimum: 0),
    ]);

    $split = PaymentStageSplitter::split(215_000, $stages);

    expect(array_column($split, 'amount'))->toBe([80_000, 107_500, 27_500])
        ->and(array_sum(array_column($split, 'amount')))->toBe(215_000);
});

it('drops the stages an oversized minimum leaves nothing for', function () {
    $stages = collect([
        paymentStage(25, 'Bei der Zusage', minimum: 80_000),
        paymentStage(50, 'Vier Wochen vorher', minimum: 0),
        paymentStage(25, 'Nach der Lieferung', minimum: 0),
    ]);

    $split = PaymentStageSplitter::split(75_000, $stages);

    expect($split)->toHaveCount(1)
        ->and($split[0]['amount'])->toBe(75_000)
        ->and($split[0]['label'])->toBe('Bei der Zusage');
});

// Ohne Mindestbetrag ist es der Dreisatz, auf den Euro gerundet.
it('rounds a plain share to the euro and lets the last stage absorb it', function () {
    $split = PaymentStageSplitter::split(215_050, collect([
        paymentStage(25, 'Anzahlung'),
        paymentStage(75, 'Rest'),
    ]));

    expect(array_column($split, 'amount'))->toBe([53_800, 161_250])
        ->and(array_sum(array_column($split, 'amount')))->toBe(215_050);
});

it('splits a small amount without rounding a share to ten euros', function () {
    $split = PaymentStageSplitter::split(8_900, collect([
        paymentStage(50, 'Anzahlung'),
        paymentStage(50, 'Rest'),
    ]));

    expect(array_column($split, 'amount'))->toBe([4_500, 4_400]);
});
