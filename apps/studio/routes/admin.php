<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Staff surface. Session-authenticated, Inertia-rendered, never public.
Route::get('/login', fn () => Inertia::render('Auth/Login'))->name('login');
