<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-11: the window App\Notifications\Support\QuietHours reads — a tenant-wide
 * setting, same shape as T-13's timezone/working_days on this same table,
 * because "what time is it for this studio" is a single answer regardless of
 * which staff member or client a notification is about. Disabled (false) and
 * both times null by default: an unconfigured studio sends exactly as it did
 * before this story existed, the same "the environment stays the fallback"
 * rule T-13's own migration documents.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->boolean('quiet_hours_enabled')->default(false);
            $table->time('quiet_hours_start')->nullable();
            $table->time('quiet_hours_end')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->dropColumn(['quiet_hours_enabled', 'quiet_hours_start', 'quiet_hours_end']);
        });
    }
};
