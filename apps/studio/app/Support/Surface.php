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

    /** P2 appends 'portal' here alongside routes/portal.php. */
    public const ALL = [self::ADMIN, self::API];

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
            self::API => Route::middleware('api')->prefix('api')->group($file),
            default => Route::middleware('web')->group($file),
        };
    }
}
