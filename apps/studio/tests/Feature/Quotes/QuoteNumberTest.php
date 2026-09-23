<?php

use App\Crm\Models\ClientCase;
use App\Models\User;
use App\Quotes\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('vergibt die Nummer beim Anlegen', function () {
    $user = User::factory()->withMfa()->create();
    $project = ClientCase::factory()->create();

    $this->actingAs($user, 'staff')->post("/auftraege/{$project->public_id}/angebote", [])->assertRedirect();

    expect(Quote::query()->latest('id')->first()->number)->not->toBeNull();
});

it('behaelt die Nummer beim Versenden', function () {
    $user = User::factory()->withMfa()->create();
    $project = ClientCase::factory()->create();

    $this->actingAs($user, 'staff')->post("/auftraege/{$project->public_id}/angebote", []);
    $quote = Quote::query()->latest('id')->first();
    $number = $quote->number;

    $this->actingAs($user, 'staff')->post("/angebote/{$quote->public_id}/versenden")->assertRedirect();

    expect($quote->fresh()->number)->toBe($number);
});
