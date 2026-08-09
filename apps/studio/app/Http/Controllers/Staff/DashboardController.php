<?php

namespace App\Http\Controllers\Staff;

use App\Crm\Models\Lead;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Dashboard', [
            'leads' => Lead::query()->open()->count(),
            // Job arrives with conversion in sprint 4; zero is the honest
            // answer until it exists.
            'jobs' => 0,
        ]);
    }
}
