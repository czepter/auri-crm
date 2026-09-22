<?php

use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the setup screen on a database with no staff account', function () {
    $this->get('/einrichtung')->assertOk();
});

it('creates the first owner, signs them in, and sends them to the studio wizard', function () {
    $this->post('/einrichtung', [
        'name' => 'Christian Zepter',
        'email' => 'christian@nordlicht.test',
        'password' => 'ein-sehr-langes-passwort',
        'password_confirmation' => 'ein-sehr-langes-passwort',
    ])->assertRedirect('/einrichtung/studio');

    $user = User::where('email', 'christian@nordlicht.test')->sole();

    expect($user->role)->toBe(StaffRole::Admin);
    $this->assertAuthenticatedAs($user, 'staff');
});

it('redirects GET to login and 404s POST once a staff account exists', function () {
    User::factory()->create();

    $this->get('/einrichtung')->assertRedirect('/login');
    $this->post('/einrichtung', [
        'name' => 'Zweite Person',
        'email' => 'zweite@nordlicht.test',
        'password' => 'ein-sehr-langes-passwort',
        'password_confirmation' => 'ein-sehr-langes-passwort',
    ])->assertNotFound();
});

it('rejects a weak or unconfirmed password', function () {
    $this->post('/einrichtung', [
        'name' => 'Christian Zepter',
        'email' => 'christian@nordlicht.test',
        'password' => 'short',
        'password_confirmation' => 'different',
    ])->assertSessionHasErrors('password');

    expect(User::query()->exists())->toBeFalse();
});

it('redirects the login form to setup while no staff account exists', function () {
    $this->get('/login')->assertRedirect('/einrichtung');
});
