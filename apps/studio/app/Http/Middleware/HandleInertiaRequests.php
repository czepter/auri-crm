<?php

namespace App\Http\Middleware;

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
        return [
            ...parent::share($request),
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
            ],
        ];
    }
}
