<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // T-19: rungs of the reminder ladder already climbed. A count and
            // not a timestamp, so a scheduler that stood a week sends one
            // message instead of seven — see FibonacciReminderSchedule.
            $table->unsignedSmallInteger('reminders_sent')->default(0);
            // T-28: the client's own ladder on the same due date, one rung
            // behind — the studio may learn of a slipping task earlier and
            // more often than the client is chased about it.
            $table->unsignedSmallInteger('client_reminders_sent')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['reminders_sent', 'client_reminders_sent']);
        });
    }
};
