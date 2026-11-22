<?php

use App\Enums\StaffRole;
use App\Finance\Models\Expense;
use App\Finance\Models\FinanceDocument;
use App\Finance\Models\FixedAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('answers 404 to every role without the finance capability', function (StaffRole $role) {
    $expense = Expense::factory()->create();
    $document = FinanceDocument::factory()->create();
    $asset = FixedAsset::factory()->create();
    $this->actingAs(staffMember($role), 'staff');

    foreach ([
        ['get', '/finanzen/ausgaben'], ['get', '/finanzen/ausgaben/neu'], ['post', '/finanzen/ausgaben'],
        ['get', "/finanzen/ausgaben/{$expense->public_id}"], ['put', "/finanzen/ausgaben/{$expense->public_id}"],
        ['post', "/finanzen/ausgaben/{$expense->public_id}/status"], ['delete', "/finanzen/ausgaben/{$expense->public_id}"],
        ['post', '/finanzen/belege'], ['get', "/finanzen/belege/{$document->public_id}"],
        ['get', '/finanzen/anlagen'], ['post', "/finanzen/anlagen/{$asset->public_id}/abgang"], ['delete', "/finanzen/anlagen/{$asset->public_id}/abgang"],
        ['get', '/finanzen/euer'], ['patch', '/finanzen/euer/2025/ust'], ['post', '/finanzen/euer/2025/sperren'], ['get', '/finanzen/euer/2025/pdf'],
    ] as [$method, $path]) {
        $this->{$method}($path)->assertNotFound();
    }
})->with([StaffRole::Manager, StaffRole::Contributor, StaffRole::Reader]);

it('lets the admin in and sends /finanzen to the expenses', function () {
    $this->actingAs(staffMember(), 'staff');

    $this->get('/finanzen')->assertRedirect('/finanzen/ausgaben');
    $this->get('/finanzen/ausgaben')->assertOk();
});
