<?php

namespace App\Http\Controllers\Staff;

use App\Crm\Models\Job;
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
            'jobs' => Job::query()->count(),
        ]);
    }
}
