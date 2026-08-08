<?php

use Illuminate\Support\Facades\Route;

// Staff surface. Session-authenticated, Inertia-rendered, never public.
// Task 3 renders the login page here; Tasks 7 and 8 give it a backend.
Route::get('/login', fn () => 'login placeholder')->name('login');
