<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        // Real counts arrive in P1b with leads and jobs. The page exists now
        // so the MFA gate and the audience gate have something to protect.
        return Inertia::render('Dashboard', [
            'leads' => 0,
            'jobs' => 0,
        ]);
    }
}
