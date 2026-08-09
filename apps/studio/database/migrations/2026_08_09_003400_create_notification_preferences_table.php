<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-11: one row per (notifiable, notification, channel) an owner or a client
 * has turned off — absence of a row means "on", the opt-out shape both
 * "which events notify a staff member" and "unsubscribe" need. Read
 * exclusively by App\Notifications\Support\NotificationPreferences, and only
 * from inside App\Notifications\NotificationService::send() — never by a
 * Transport directly, the same "one choke point" rule E-07 established for
 * the transports themselves.
 *
 * notification_class is a FQCN, same identity Notification::via() and
 * InAppTransport's own `type` column already key on — never a second,
 * hand-maintained event taxonomy invented for this table alone. The literal
 * string '*' is the one reserved value: a client's one-click unsubscribe
 * writes a single '*' row per channel rather than one row per notification
 * class, so a future notification class is opted out by the same click that
 * covered every class that existed when it was clicked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->morphs('notifiable');
            $table->string('notification_class');
            $table->string('channel');
            $table->boolean('enabled');

            $table->timestampsTz();

            $table->unique(
                ['tenant_id', 'notifiable_type', 'notifiable_id', 'notification_class', 'channel'],
                'notification_preferences_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
