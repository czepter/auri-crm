<?php

use App\Http\Controllers\Staff\DashboardController;
use App\Http\Controllers\Staff\SessionController;
use Illuminate\Support\Facades\Route;

// Staff surface. Session-authenticated, Inertia-rendered, never public.
Route::middleware('guest:staff')->group(function () {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store']);
});

Route::middleware('auth:staff')->group(function () {
    Route::post('/logout', [SessionController::class, 'destroy']);
    Route::get('/', DashboardController::class)->name('dashboard');
});
