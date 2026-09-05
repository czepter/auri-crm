<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    /**
     * Every controller can call $this->authorize(). The trait is not on by
     * default in Laravel 11, and a policy nobody can invoke is worse than no
     * policy at all — it reads as protection while granting everything.
     */
    use AuthorizesRequests;
}
