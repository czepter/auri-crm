<?php

use App\Enums\StaffRole;
use App\Finance\Models\Expense;
use App\Finance\Models\FinanceDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('answers 404 to every role without the finance capability', function (StaffRole $role) {
    $expense = Expense::factory()->create();
    $document = FinanceDocument::factory()->create();
    $this->actingAs(staffMember($role), 'staff');

    foreach ([
        ['get', '/finanzen/ausgaben'], ['get', '/finanzen/ausgaben/neu'], ['post', '/finanzen/ausgaben'],
        ['get', "/finanzen/ausgaben/{$expense->public_id}"], ['put', "/finanzen/ausgaben/{$expense->public_id}"],
        ['post', "/finanzen/ausgaben/{$expense->public_id}/status"], ['delete', "/finanzen/ausgaben/{$expense->public_id}"],
        ['post', '/finanzen/belege'], ['get', "/finanzen/belege/{$document->public_id}"],
    ] as [$method, $path]) {
        $this->{$method}($path)->assertNotFound();
    }
})->with([StaffRole::Manager, StaffRole::Contributor, StaffRole::Reader]);

it('lets the admin in and sends /finanzen to the expenses', function () {
    $this->actingAs(staffMember(), 'staff');

    $this->get('/finanzen')->assertRedirect('/finanzen/ausgaben');
    $this->get('/finanzen/ausgaben')->assertOk();
});
