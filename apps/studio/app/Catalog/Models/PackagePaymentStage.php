<?php

namespace App\Catalog\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Q-11's template half — copied onto a quote by Package::copyPaymentStages(), never referenced. */
class PackagePaymentStage extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $fillable = ['package_id', 'percent', 'label', 'minimum', 'sort_order'];

    protected $attributes = ['minimum' => 0, 'sort_order' => 0];

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }
}
