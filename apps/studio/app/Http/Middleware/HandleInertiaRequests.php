<?php

namespace App\Http\Middleware;

use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use App\Data\StaffProfileData;
use App\Support\Surface;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * Each surface has its own root view, so each surface loads its own Vite
     * entrypoint and its own theme. There is no shared HTML shell.
     */
    public function rootView(Request $request): string
    {
        return 'app-'.Surface::current();
    }

    public function share(Request $request): array
    {
        $user = $request->user('staff');

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user === null ? null : StaffProfileData::fromModel($user),
            ],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
            ],
            // The sidebar's counts. Lazy closures, so they cost nothing on the
            // login page and nothing on a partial reload that does not ask for
            // them.
            //
            // ponytail: two counts per authenticated request, unbounded by
            // date. Fine at a studio's volume; if the board ever pages, these
            // become one grouped query instead.
            'nav' => $user === null ? null : [
                'leads' => fn () => Lead::query()->open()->count(),
                'projects' => fn () => Project::query()->count(),
            ],
        ];
    }
}
