<?php

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

// Public JSON surface. In P1a this carries the health probe only; P1b adds
// the public inquiry endpoint and P3 adds the gallery read API.
Route::get('/health', HealthController::class);
