<?php

namespace App\Payments\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Crm\Models\Job;
use App\Payments\Enums\PaymentInstallmentStatus;
use Database\Factories\Payments\PaymentPlanFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One plan per Job — the unique index on job_id (see the migration) is what
 * makes that a database fact rather than a convention. Scoped to the Job, not
 * the Project: the plan is a property of the booking, never of the pursuit,
 * so a project with no Job has nowhere for one to hang (M-03).
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

    protected $fillable = ['job_id', 'total', 'reference', 'client_reminders_enabled'];

    protected $attributes = ['client_reminders_enabled' => true];

    protected function casts(): array
    {
        return ['client_reminders_enabled' => 'boolean'];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(PaymentInstallment::class)->orderBy('sort_order');
    }

    /**
     * A plan stays editable until its first installment (by sort order) is
     * invoiced. Nothing in M-03 ever writes PaymentInstallmentStatus::Invoiced
     * — this guard exists now, defensively, so M-04's real invoicing does not
     * have to revisit it: the moment something sets that status, editing
     * stops here without this method changing at all.
     */
    public function isEditable(): bool
    {
        $first = $this->installments()->orderBy('sort_order')->first();

        return $first === null || $first->status !== PaymentInstallmentStatus::Invoiced;
    }

    protected static function newFactory(): Factory
    {
        return PaymentPlanFactory::new();
    }
}
