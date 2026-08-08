<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

// The web middleware group starts a database-backed session on every request.
uses(RefreshDatabase::class);

it('renders the login page as an inertia component', function () {
    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
});

it('renders through the admin root view', function () {
    $response = $this->get('/login')->assertOk();

    expect($response->original->name())->toBe('app-admin');
});
