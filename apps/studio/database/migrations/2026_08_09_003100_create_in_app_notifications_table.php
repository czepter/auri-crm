<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E-07: App\Notifications\Transports\InAppTransport's own ledger — the
 * second channel NotificationService can fan a notification out to besides
 * mail. notifiable_type/notifiable_id is a standard polymorphic pair rather
 * than a foreign key to one table: App\Models\User is the only Notifiable
 * model in this codebase today (App\Crm\Models\Contact carries no Notifiable
 * trait — see App\Quotes\Notifications\QuoteSent's own docblock), but
 * nothing here assumes it stays the only one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('in_app_notifications', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->publicId();

            $table->morphs('notifiable');
            $table->string('type');
            $table->jsonb('data')->default('{}');
            $table->timestampTz('read_at')->nullable();

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('in_app_notifications');
    }
};
