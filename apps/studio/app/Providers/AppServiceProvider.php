<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Blueprint::macro('tenantId', function () {
            /** @var Blueprint $this */
            $column = $this->uuid('tenant_id')->default(config('tenancy.tenant_id'));
            $this->index('tenant_id');

            return $column;
        });

        Blueprint::macro('publicId', function () {
            /** @var Blueprint $this */
            return $this->char('public_id', 24)->unique();
        });

        // Filling a non-fillable attribute is silent by default, which turns a
        // factory state like withMfa() into a no-op that fails a test three tasks
        // later. Outside production, make it throw at the point of the mistake.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
    }
}
