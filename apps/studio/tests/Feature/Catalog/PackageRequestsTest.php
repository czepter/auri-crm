<?php

use App\Catalog\Models\Package;
use App\Crm\Enums\CaseContactRole;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Crm\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Zwei Anfragen auf dasselbe Paket, die ältere gebucht — und eine dritte auf
// ein anderes Paket, die in keiner der beiden Zahlen auftauchen darf.
function requestedTwice(Package $package): ClientCase
{
    $booked = ClientCase::factory()->create();
    Lead::factory()->create([
        'case_id' => $booked->id,
        'package_id' => $package->id,
        'captured_at' => now()->subMonth(),
    ]);
    markBooked($booked);

    $open = ClientCase::factory()->create();
    Lead::factory()->create([
        'case_id' => $open->id,
        'package_id' => $package->id,
        'captured_at' => now(),
    ]);

    Lead::factory()->create(['package_id' => Package::factory()->create()->id]);

    return $open;
}

it('counts requests and bookings per package', function () {
    $package = Package::factory()->create(['name' => 'Aaa Fotoalbum', 'portal_offered' => true]);
    requestedTwice($package);

    $this->actingAs(User::factory()->withMfa()->create(), 'staff')
        ->get('/pakete')
        ->assertInertia(fn ($page) => $page
            ->where('packages.0.name', 'Aaa Fotoalbum')
            ->where('packages.0.requestedCount', 2)
            ->where('packages.0.bookedCount', 1));
});

it('lists the requests that started from one package, newest first', function () {
    $package = Package::factory()->create(['portal_offered' => true]);
    $open = requestedTwice($package);
    $open->attachContact(Contact::factory()->create(['name' => 'Viola Neumann']), CaseContactRole::Primary);

    $this->actingAs(User::factory()->withMfa()->create(), 'staff')
        ->get("/pakete/{$package->public_id}")
        ->assertInertia(fn ($page) => $page
            ->has('requests', 2)
            ->where('requests.0.contact', 'Viola Neumann')
            ->where('requests.0.booked', false)
            ->where('requests.1.booked', true));
});
