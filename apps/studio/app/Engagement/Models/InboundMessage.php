<?php

namespace App\Engagement\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Crm\Models\Contact;
use App\Engagement\Enums\InboundMessageState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One inbound delivery, kept whether or not it threaded — see the migration
 * for why every one of them gets a row.
 */
class InboundMessage extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $fillable = [
        'message_id', 'from_address', 'to_address', 'subject', 'body',
        'spf', 'dkim', 'dmarc', 'spam_score',
        'state', 'reason', 'thread_id', 'message_id_ref', 'contact_id',
    ];

    protected function casts(): array
    {
        return ['state' => InboundMessageState::class];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
