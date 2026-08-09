<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $query) {
            $query->where($query->getModel()->getTable().'.tenant_id', config('tenancy.tenant_id'));
        });

        static::creating(function (Model $model) {
            $model->tenant_id ??= config('tenancy.tenant_id');
        });
    }
}
