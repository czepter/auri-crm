<?php

namespace App\Automation\Models;

use App\Automation\Actions\FireAutomationRule;
use App\Automation\Enums\AutomationRunStatus;
use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Enums\Capability;
use App\Models\User;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Models\PaymentInstallment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One row per firing of an AutomationRule — the audit trail and the
 * idempotency guard both live here. anchorable is the entity the rule fired
 * against: a Shoot for a `per_shoot` date-offset rule, or a Project for a
 * `primary_shoot` one or an event-triggered one (an event's own subject —
 * see App\Automation\Listeners\FireAutomationRulesForEvent).
 *
 * No HasFactory: every row this app ever writes goes through
 * App\Automation\Actions\FireAutomationRule, which is what a test drives
 * instead of building rows by hand — a factory here would be a second,
 * unchecked way to produce a state the real code path can never actually
 * reach.
 */
class AutomationRun extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $fillable = [
        'automation_rule_id',
        'anchorable_type',
        'anchorable_id',
        'idempotency_key',
        'fired_at',
        'status',
        'error',
        'subject',
        'body',
        'contact_ids',
    ];

    protected function casts(): array
    {
        return [
            'fired_at' => 'immutable_datetime',
            'status' => AutomationRunStatus::class,
            'contact_ids' => 'array',
        ];
    }

    /** @return Collection<int, Contact> */
    public function contacts(): Collection
    {
        return Contact::query()->whereIn('id', $this->contact_ids ?? [])->get();
    }

    /**
     * The client mails waiting for a send that this user may open; the
     * Posteingang list and the sidebar badge both read it.
     *
     * @return Collection<int, AutomationRun>
     */
    public static function awaitingFor(User $user): Collection
    {
        return static::query()
            ->where('status', AutomationRunStatus::AwaitingApproval)
            ->with('anchorable')
            ->latest('fired_at')
            ->get()
            ->filter(fn (AutomationRun $run) => $run->anchorable === null
                ? $user->can(Capability::ViewAllCases->value)
                : ($project = FireAutomationRule::projectOf($run->anchorable)) === null
                    || ($user->can('view', $project) && $run->paymentDraftOpenFor($user, $project)))
            ->values();
    }

    /**
     * A reminder draft is the client's money chase: it stays with whoever may
     * manage the payment plan, and ends when the rate is no longer owed.
     */
    public function paymentDraftOpenFor(User $user, ClientCase $project): bool
    {
        return ! $this->anchorable instanceof PaymentInstallment
            || (in_array($this->anchorable->status, PaymentInstallmentStatus::open(), true)
                && $user->can('managePaymentPlan', $project));
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AutomationRule::class, 'automation_rule_id');
    }

    public function anchorable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The unique constraint automation_runs' own migration puts on
     * (tenant_id, idempotency_key) is what actually enforces this; this
     * method only computes the value both the claim insert and the upfront
     * existence check in FireAutomationRule key on.
     */
    public static function idempotencyKey(AutomationRule $rule, Model $anchor, string $triggerDate): string
    {
        return hash('sha256', implode('|', [
            $rule->getKey(),
            $anchor->getMorphClass(),
            $anchor->getKey(),
            $triggerDate,
        ]));
    }
}
