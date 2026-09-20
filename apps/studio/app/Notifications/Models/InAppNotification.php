<?php

namespace App\Notifications\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One row per send through App\Notifications\Transports\InAppTransport.
 * Nothing but that transport writes one — see its own docblock.
 */
class InAppNotification extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $table = 'in_app_notifications';

    protected $fillable = ['notifiable_type', 'notifiable_id', 'type', 'project_id', 'data', 'read_at'];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'immutable_datetime',
        ];
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }
}
