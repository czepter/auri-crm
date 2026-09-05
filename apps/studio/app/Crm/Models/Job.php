<?php

namespace App\Crm\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Crm\Enums\BookedVia;
use App\Models\User;
use App\Payments\Models\PaymentPlan;
use Database\Factories\Crm\JobFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * The commitment: this project is booked, by whom and through which trigger.
 *
 * Not the queue table of the same name — that one is queue_jobs, renamed in the
 * framework's own migration so the domain could have the word.
 */
class Job extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = ['project_id', 'booked_at', 'booked_by_id', 'booked_via'];

    protected function casts(): array
    {
        return [
            'booked_at' => 'immutable_datetime',
            'booked_via' => BookedVia::class,
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function bookedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booked_by_id');
    }

    /** Absent until CreatePaymentPlan writes it (M-03) — one plan per job, at most. */
    public function paymentPlan(): HasOne
    {
        return $this->hasOne(PaymentPlan::class);
    }

    protected static function newFactory(): Factory
    {
        return JobFactory::new();
    }
}
