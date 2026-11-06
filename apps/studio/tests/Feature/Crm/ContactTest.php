<?php

use App\Crm\Models\Contact;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('creates a contact with a tenant and a public id', function () {
    $contact = Contact::factory()->create(['name' => 'Annika Steffens']);

    expect($contact->tenant_id)->toBe(config('tenancy.tenant_id'))
        ->and($contact->public_id)->toHaveLength(24)
        ->and($contact->getRouteKeyName())->toBe('public_id');
});

it('hides the primary key from serialization', function () {
    expect(Contact::factory()->create()->toArray())->not->toHaveKey('id');
});

it('rejects a second contact with the same email', function () {
    Contact::factory()->create(['email' => 'a.steffens@posteo.de']);
    Contact::factory()->create(['email' => 'a.steffens@posteo.de']);
})->throws(QueryException::class);

it('allows any number of contacts without an email', function () {
    // A venue coordinator recorded for reference has no address, which is
    // honest — there is no way to send them a portal link either.
    Contact::factory()->withoutEmail()->count(3)->create();

    expect(Contact::whereNull('email')->count())->toBe(3);
});

it('allows the same email under a different tenant', function () {
    Contact::factory()->create(['email' => 'shared@example.test']);

    DB::table('contacts')->insert([
        'tenant_id' => '00000000-0000-4000-8000-00000000dead',
        'public_id' => 'bbbbbbbbbbbbbbbbbbbbbbbb',
        'name' => 'Someone Else',
        'email' => 'shared@example.test',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(Contact::count())->toBe(1)
        ->and(DB::table('contacts')->count())->toBe(2);
});

it('files a contact with only an inquiry under Ohne Auftrag', function () {
    $contact = Contact::factory()->create();
    $inquiry = \App\Crm\Models\ClientCase::factory()->wedding()->create(['booked_at' => null]);
    $inquiry->attachContact($contact, \App\Crm\Enums\CaseContactRole::Primary);

    $row = \App\Crm\Data\ContactRowData::fromContact($contact->load('cases.primaryShoot'));

    expect($row->standing)->toBe('none');

    \App\Crm\Models\ClientCase::factory()->wedding()->booked()->create()
        ->attachContact($contact, \App\Crm\Enums\CaseContactRole::Primary);

    expect(\App\Crm\Data\ContactRowData::fromContact($contact->fresh()->load('cases.primaryShoot'))->standing)->toBe('past');
});
