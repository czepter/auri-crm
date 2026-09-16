<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;
use InvalidArgumentException;

/**
 * The single place that knows a surface exists. Adding the client portal in
 * P2 means adding one constant, one entry in ALL, and one route file.
 */
class Surface
{
    public const ADMIN = 'admin';
    public const API = 'api';
    public const PORTAL = 'portal';

    public const ALL = [self::ADMIN, self::API, self::PORTAL];

    /**
     * The session cookie each surface signs its clients with.
     *
     * Distinct names rather than one shared cookie: a staff session and a
     * client session must never be interchangeable, and two audiences sharing
     * a cookie name on one host is how one becomes the other. The guard would
     * still refuse, but only after the browser had already offered the wrong
     * credential to the wrong audience.
     */
    public const COOKIES = [
        self::ADMIN => 'studio_admin_session',
        self::API => 'studio_api_session',
        self::PORTAL => 'studio_portal_session',
    ];

    public static function current(): string
    {
        return (string) config('surface.name');
    }

    public static function registerRoutes(): void
    {
        $surface = self::current();

        if (! in_array($surface, self::ALL, true)) {
            throw new InvalidArgumentException(sprintf(
                'Unknown APP_SURFACE "%s". Expected one of: %s.',
                $surface,
                implode(', ', self::ALL),
            ));
        }

        $file = base_path("routes/{$surface}.php");

        match ($surface) {
            // Two files, because the MCP endpoint and its discovery document
            // are addressed at the host root rather than under /api — see
            // routes/mcp.php.
            self::API => Route::middleware('api')->group(function () use ($file) {
                Route::group([], base_path('routes/mcp.php'));
                Route::prefix('api')->group($file);
            }),
            default => Route::middleware('web')->group($file),
        };
    }
}
