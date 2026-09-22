<?php

use App\Crm\Models\Contact;
use App\Enums\StaffRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('exports contacts as a semicolon CSV without erased ones', function () {
    Contact::factory()->create(['name' => 'Lena Brandt', 'email' => 'lena@example.test']);
    Contact::factory()->create(['name' => 'Gelöscht', 'dsgvo_erased_at' => now()]);

    $csv = $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->get('/einstellungen/export/kontakte')
        ->assertOk()
        ->streamedContent();

    $lines = explode("\n", trim(ltrim($csv, "\u{FEFF}")));

    expect($lines)->toHaveCount(2)
        ->and(str_getcsv($lines[1], ';'))->toMatchArray([0 => 'Lena Brandt', 1 => 'lena@example.test']);
});

it('hides the export from a role without settings', function () {
    $this->actingAs(staffMember(StaffRole::Manager), 'staff')
        ->get('/einstellungen/export/kontakte')
        ->assertNotFound();
});
