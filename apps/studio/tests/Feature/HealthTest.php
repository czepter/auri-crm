<?php

use App\Support\Surface;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    // The suite defaults to the admin surface; health lives on api.
    config(['surface.name' => Surface::API]);
    Route::setRoutes(new RouteCollection);
    Surface::registerRoutes();
});

it('reports service and database health', function () {
    $this->getJson('/api/health')
        ->assertOk()
        ->assertExactJson(['status' => 'ok', 'database' => 'ok']);
});
