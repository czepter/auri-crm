<?php

namespace App\Crm\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Crm\Enums\TaskAudience;
use App\Crm\Support\TaskAnchors;
use App\Models\User;
use Database\Factories\Crm\TaskFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One piece of work the studio owes, anchored to the occasion it belongs to.
 */
class Task extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = [
        'project_id',
        'shoot_id',
        'title',
        'audience',
        'due_on',
        'due_anchor',
        'due_offset_days',
        'completed_at',
        'completed_by_id',
        'assignee_id',
        'reminders_sent',
        'client_reminders_sent',
        'task_template_id',
        'depends_on_task_id',
    ];

    protected function casts(): array
    {
        return [
            'audience' => TaskAudience::class,
            'due_on' => 'immutable_date',
            'completed_at' => 'immutable_datetime',
        ];
    }

    /**
     * The occasion the due date hangs off, as a screen reads it. Null for a
     * fixed date, and null again once the shoot behind the anchor is gone.
     */
    public function dueAnchorLabel(): ?string
    {
        return $this->due_anchor === null
            ? null
            : TaskAnchors::label($this->project, $this->due_anchor);
    }

    public function isDone(): bool
    {
        return $this->completed_at !== null;
    }

    /**
     * S-12: waiting on something that is not finished yet.
     *
     * A blocked task is still on every list it was on before — what the
     * studio is waiting for is exactly the thing it needs to see. Only
     * completing it is refused.
     */
    public function isBlocked(): bool
    {
        return $this->dependsOn !== null && ! $this->dependsOn->isDone();
    }

    public function dependsOn(): BelongsTo
    {
        return $this->belongsTo(self::class, 'depends_on_task_id');
    }

    public function dependents(): HasMany
    {
        return $this->hasMany(self::class, 'depends_on_task_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function shoot(): BelongsTo
    {
        return $this->belongsTo(Shoot::class);
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_id');
    }

    /** T-24: who owes it. Null falls back to the crew, then to the studio — see StudioAudience::forTask(). */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * Still owed. Every read that asks "what does the studio have to do" —
     * the project card, the Übersicht, the readiness marks — goes through here
     * rather than repeating the null check.
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('completed_at');
    }

    /** Undated tasks sort last: they are owed, but not owed on any given day. */
    public function scopeByDueDate(Builder $query): void
    {
        $query->orderByRaw('due_on is null, due_on');
    }

    protected static function newFactory(): Factory
    {
        return TaskFactory::new();
    }
}
