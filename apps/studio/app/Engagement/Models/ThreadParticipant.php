<?php

namespace App\Engagement\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Crm\Models\Contact;
use App\Models\User;
use Database\Factories\Engagement\ThreadParticipantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/**
 * Who is on a Thread — the contact and every staff member who has posted to
 * it, written once each by App\Engagement\Actions\PostMessage rather than
 * managed through any screen of its own.
 */
class ThreadParticipant extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = ['thread_id', 'contact_id', 'user_id'];

    public static function booted(): void
    {
        static::creating(function (self $participant) {
            if (($participant->contact_id === null) === ($participant->user_id === null)) {
                throw new InvalidArgumentException(
                    'A thread participant is exactly one party: a contact or a staff user, never both and never neither.'
                );
            }
        });
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function newFactory(): Factory
    {
        return ThreadParticipantFactory::new();
    }
}
