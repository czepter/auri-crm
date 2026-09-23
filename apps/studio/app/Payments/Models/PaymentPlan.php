<?php

namespace App\Payments\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Crm\Models\Project;
use Database\Factories\Payments\PaymentPlanFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One plan per project — the unique index on project_id is what makes that a
 * database fact rather than a convention. The plan is a property of the
 * booking, never of the pursuit: an unbooked project gets none (M-03).
 *
 * `total` is the authoritative figure, not a sum of the installments —
 * installments are what must add up to it, in the other direction, and the
 * database itself refuses a commit where they do not (see the constraint
 * trigger in 2026_08_09_002500). That is also why total is written directly
 * by App\Payments\Actions\CreatePaymentPlan and UpdatePaymentPlan, never
 * recalculated from a relation the way Quote::recalculateTotal() sums up
 * from its items — here the total leads and the installments follow it.
 */
class PaymentPlan extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = ['project_id', 'total', 'reference', 'settled_at'];

    protected function casts(): array
    {
        return ['settled_at' => 'immutable_datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(PaymentInstallment::class)->orderBy('sort_order');
    }

    /** #418: closed. Nothing chases it, nothing charges on it, nothing edits it. */
    public function isSettled(): bool
    {
        return $this->settled_at !== null;
    }

    protected static function newFactory(): Factory
    {
        return PaymentPlanFactory::new();
    }
}
