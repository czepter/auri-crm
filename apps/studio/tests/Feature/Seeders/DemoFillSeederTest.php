<?php

use App\Catalog\Models\Package;
use App\Contracts\Models\Contract;
use App\Crm\Models\ClientCase;
use App\Crm\Models\FormAnswer;
use App\Documents\Models\DocumentTemplate;
use App\Engagement\Models\Message;
use App\Models\Absence;
use App\Models\StudioClosure;
use App\Payments\Models\CreditNote;
use App\Payments\Models\Invoice;
use App\Quotes\Models\Quote;
use Database\Seeders\DemoCrmSeeder;
use Database\Seeders\DemoFillSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('adds demo content once and changes nothing on a second run', function () {
    $this->seed(DemoCrmSeeder::class);
    $this->seed(DemoFillSeeder::class);

    $counts = fn () => [
        Package::count(), DocumentTemplate::count(), Contract::count(), Invoice::count(), CreditNote::count(),
        Quote::count(), FormAnswer::count(), Absence::count(), StudioClosure::count(), Message::count(),
    ];

    $first = $counts();
    $meier = ClientCase::where('name', 'Meier & Kowalski')->firstOrFail();

    expect($first)->each->toBeGreaterThan(0)
        ->and($meier->quotes()->latest('id')->first()->vat_amount)->not->toBeNull();

    $this->seed(DemoFillSeeder::class);
    $this->seed(DemoFillSeeder::class);

    expect($counts())->toBe($first);
});

it('seeds no sent quote without a number or a frozen total, and one accepted quote per accepted lead', function () {
    $this->seed(DemoCrmSeeder::class);
    $this->seed(DemoFillSeeder::class);

    $sent = Quote::whereNotNull('sent_at')->get();
    $counts = App\Quotes\Support\QuoteAcceptanceRate::counts(now()->subYear(), now());

    expect($sent->whereNull('number'))->toBeEmpty()
        ->and($sent->whereNull('vat_amount'))->toBeEmpty()
        ->and($counts['accepted'])->toBe(3);
});
