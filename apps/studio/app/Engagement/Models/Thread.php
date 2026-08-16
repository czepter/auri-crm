<?php

namespace App\Engagement\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Crm\Models\Project;
use Database\Factories\Engagement\ThreadFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A conversation with a client about one project (E-06). Client-initiable —
 * the client opens the first message, not just replies to a studio one — is
 * what makes this a channel rather than a notification log.
 *
 * Holds no visibility flag and never will: InternalNote (C-07) is a client's
 * business the studio keeps to itself, in its own table, and Message below
 * carries no flag that could put one of its rows there instead.
 */
class Thread extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = ['project_id', 'subject'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->oldest();
    }

    /**
     * The one message that decides whether this thread is unanswered: whoever
     * sent the newest line owes (or is owed) the next one. `latestOfMany`
     * keeps that a single indexed lookup — Eloquent's own subquery, not a
     * hand-rolled correlated one — and a thread with no messages simply has
     * no latestMessage, which is what makes it fall out of that count too.
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ThreadParticipant::class);
    }

    protected static function newFactory(): Factory
    {
        return ThreadFactory::new();
    }
}
