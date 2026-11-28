<?php

namespace App\Engagement\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Task;
use Database\Factories\Engagement\ThreadFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A conversation with a client (E-06). Client-initiable — the client opens
 * the first message, not just replies to a studio one — is what makes this a
 * channel rather than a notification log.
 *
 * case_id is nullable (P-04, #223). A thread's booking is a reference it
 * carries, not a precondition for existing: the question that comes before
 * the booking is exactly the one a client most needs to ask. Null reads as
 * "Allgemein" on both surfaces, and AttachThreadToCase fills it in when a
 * general conversation turns out to be about a specific order after all.
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

    protected $fillable = ['case_id', 'subject'];

    /**
     * C-14 (#250): where this conversation stands. Two of the three are the
     * newest message's own author and cannot go stale; the third is a
     * decision somebody took, recorded against the message it was taken on —
     * a client who writes again has reopened the conversation whatever the
     * studio filed, and no clock is consulted to notice.
     */
    public function workState(): string
    {
        $latest = $this->latestMessage;

        if ($latest === null) {
            return 'erledigt';
        }

        if ($this->closed_message_id === $latest->id) {
            return 'erledigt';
        }

        return $latest->contact_id === null ? 'wartet' : 'unbeantwortet';
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(ClientCase::class);
    }

    /**
     * Oldest first, with the insertion order as the tiebreaker: two messages
     * written inside the same second — a client's question and the studio's
     * reply, on a busy day — otherwise come back in whatever order the
     * database happens to pick, and a conversation read out of order is a
     * different conversation.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->oldest()->orderBy('id');
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
        // By when it was said, not by insertion: RecordMessage writes lines in
        // after the fact, and an old one must not become the newest.
        return $this->hasOne(Message::class)->latestOfMany('created_at');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
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
