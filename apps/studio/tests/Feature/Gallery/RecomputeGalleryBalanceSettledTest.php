<?php

use App\Crm\Models\Job;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Gallery\Models\Gallery;
use App\Payments\Actions\MarkInstallmentPaid;
use App\Payments\Enums\SettledVia;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Models\PaymentPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('defaults requires_settled_balance and balance_settled to false', function () {
    $gallery = Gallery::factory()->create();

    expect($gallery->requires_settled_balance)->toBeFalse()
        ->and($gallery->balance_settled)->toBeFalse();
});

it('flips balance_settled to true once every installment on the project is paid', function () {
    $project = Project::factory()->create();
    $job = Job::factory()->for($project)->create();
    $gallery = Gallery::factory()->for($project)->create();

    $plan = PaymentPlan::factory()->for($job)->create(['total' => 200000]);
    $first = PaymentInstallment::factory()->for($plan, 'plan')->create(['sort_order' => 0, 'amount' => 100000]);
    $second = PaymentInstallment::factory()->for($plan, 'plan')->create(['sort_order' => 1, 'amount' => 100000]);

    app(MarkInstallmentPaid::class)($first, SettledVia::Transfer, '2026-09-01', null);
    expect($gallery->fresh()->balance_settled)->toBeFalse();

    app(MarkInstallmentPaid::class)($second, SettledVia::Transfer, '2026-10-01', null);
    expect($gallery->fresh()->balance_settled)->toBeTrue();
});

it('recomputes for every gallery on the project, per-shoot and project-wide alike', function () {
    $project = Project::factory()->create();
    $job = Job::factory()->for($project)->create();
    $projectWide = Gallery::factory()->for($project)->create();
    $perShoot = Gallery::factory()->for($project)->create(['shoot_id' => Shoot::factory()->for($project)->create()->id]);

    $plan = PaymentPlan::factory()->for($job)->create(['total' => 100000]);
    $installment = PaymentInstallment::factory()->for($plan, 'plan')->create(['sort_order' => 0, 'amount' => 100000]);

    app(MarkInstallmentPaid::class)($installment, SettledVia::Transfer, '2026-09-01', null);

    expect($projectWide->fresh()->balance_settled)->toBeTrue()
        ->and($perShoot->fresh()->balance_settled)->toBeTrue();
});

it('leaves an unrelated project untouched by a payment on a different one', function () {
    $projectA = Project::factory()->create();
    $jobA = Job::factory()->for($projectA)->create();
    $galleryA = Gallery::factory()->for($projectA)->create();

    $galleryB = Gallery::factory()->create();

    $plan = PaymentPlan::factory()->for($jobA)->create(['total' => 100000]);
    $installment = PaymentInstallment::factory()->for($plan, 'plan')->create(['sort_order' => 0, 'amount' => 100000]);

    app(MarkInstallmentPaid::class)($installment, SettledVia::Transfer, '2026-09-01', null);

    expect($galleryA->fresh()->balance_settled)->toBeTrue()
        ->and($galleryB->fresh()->balance_settled)->toBeFalse();
});
