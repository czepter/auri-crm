<?php

namespace App\Support\Observability;

use App\Support\Surface;
use Sentry\State\Scope;

/**
 * F-11: the one thing that makes admin, portal and api errors separable in
 * GlitchTip's inbox. `Surface::current()` is fixed for the whole process
 * (APP_SURFACE is set once, at boot, per container — see App\Support\
 * Surface and F-02), so this only needs to run once, from
 * App\Providers\ObservabilityServiceProvider::boot(), rather than per
 * request.
 *
 * A small invokable rather than an inline closure in the provider so the
 * acceptance criterion — "an exception raised on each surface arrives
 * tagged with that surface and no other" — is testable directly against
 * this class, without a live event or a network call. See
 * tests/Feature/Observability/SurfaceTagTest.php.
 */
class TagSurface
{
    public function __invoke(Scope $scope): void
    {
        $scope->setTag('surface', Surface::current());
    }
}
