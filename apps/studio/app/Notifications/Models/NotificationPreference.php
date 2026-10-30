<?php

namespace App\Notifications\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Notifications\Enums\NotificationMode;
use Illuminate\Database\Eloquent\Model;

/**
 * One row of "this notifiable turned this notification/channel pair off" —
 * see the migration's own docblock for the '*' wildcard notification_class
 * an unsubscribe writes. Never read outside App\Notifications\Support\
 * NotificationPreferences, the same "one resolver, never the raw row"
 * discipline StudioSettings keeps with StudioSettings::current().
 */
class NotificationPreference extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $fillable = ['notifiable_type', 'notifiable_id', 'notification_class', 'channel', 'enabled', 'mode'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'mode' => NotificationMode::class];
    }
}
