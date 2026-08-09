<?php

namespace App\Crm\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Crm\Enums\TaskAudience;
use App\Models\User;
use Database\Factories\Crm\TaskFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'completed_at',
        'completed_by_id',
        'task_template_id',
    ];

    protected function casts(): array
    {
        return [
            'audience' => TaskAudience::class,
            'due_on' => 'immutable_date',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function isDone(): bool
    {
        return $this->completed_at !== null;
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
