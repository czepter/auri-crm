<?php

namespace App\Engagement\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Crm\Models\Contact;
use App\Models\User;
use Database\Factories\Engagement\MessageFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/**
 * One line in a Thread, client-visible by construction — see the migration's
 * own comment for the guarantee this model shares with it.
 *
 * Its one author is either the contact who sent it or the staff member who
 * replied, never both and never neither — the same guard the check
 * constraint on the table repeats for a write this model does not see.
 */
class Message extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    /**
     * E-11's two columns — `email_message_id` and `delivery_state` — are
     * deliberately absent: nothing but SendStaffReply may say a message was
     * delivered, and a mass-assigned delivery state would be a claim about
     * the outside world made from request input. E-13's `scheduled_for` is
     * here, because when to send is genuinely the writer's to decide.
     */
    protected $fillable = ['thread_id', 'contact_id', 'author_id', 'body', 'scheduled_for'];

    public static function booted(): void
    {
        static::creating(function (self $message) {
            if (($message->contact_id === null) === ($message->author_id === null)) {
                throw new InvalidArgumentException(
                    'A message has exactly one author: a contact or a staff user, never both and never neither.'
                );
            }
        });
    }

    protected function casts(): array
    {
        return ['scheduled_for' => 'immutable_datetime'];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    protected static function newFactory(): Factory
    {
        return MessageFactory::new();
    }
}
