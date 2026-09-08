<?php

namespace App\Crm\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Models\User;
use Carbon\CarbonInterface;
use Database\Factories\Crm\InternalNoteFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A note the studio writes to itself about a project.
 *
 * Has no portal route, no portal policy and no entry in any portal-surface
 * prop, and must never acquire one — the guarantee is structural, not a filter
 * someone remembers to apply. InternalNoteReachTest holds that line.
 *
 * Editable and deletable by any staff member: the audience is the whole studio,
 * and audit_logs already records who changed what.
 */
class InternalNote extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = ['project_id', 'author_id', 'body', 'occurred_at'];

    protected function casts(): array
    {
        return ['occurred_at' => 'immutable_datetime'];
    }

    /** What the note is about happened then; absent it, when it was written. */
    public function happenedAt(): CarbonInterface
    {
        return $this->occurred_at ?? $this->created_at;
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    protected static function newFactory(): Factory
    {
        return InternalNoteFactory::new();
    }
}
