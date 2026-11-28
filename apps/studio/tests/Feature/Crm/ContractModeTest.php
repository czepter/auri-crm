<?php

use App\Crm\Actions\ReadNextAction;
use App\Crm\Models\ClientCase;
use App\Models\StudioSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->withMfa()->create(), 'staff');
    $this->case = markBooked(ClientCase::factory()->create());
});

function contractMode(string $mode): void
{
    StudioSettings::query()->update(['contract_mode' => $mode]);
    StudioSettings::forgetCurrent();
}

function tabs(ClientCase $case): array
{
    return test()->get("/auftraege/{$case->public_id}")->viewData('page')['props']['header']['tabs'];
}

it('asks for a contract only when contracts are always', function (string $mode, string $label) {
    contractMode($mode);

    expect(app(ReadNextAction::class)($this->case)['label'])->toBe($label);
})->with([
    ['always', 'project.contractSendStep'],
    ['on_demand', 'project.formSendStep'],
    ['off', 'project.formSendStep'],
]);

it('shows the contract tab by mode', function (string $mode, bool $withContract, bool $shown) {
    contractMode($mode);

    if ($withContract) {
        renderedContract($this->case);
    }

    expect(collect(tabs($this->case))->pluck('key')->contains('contract'))->toBe($shown);
})->with([
    ['always', false, true],
    ['on_demand', false, false],
    ['on_demand', true, true],
    ['off', true, false],
]);
