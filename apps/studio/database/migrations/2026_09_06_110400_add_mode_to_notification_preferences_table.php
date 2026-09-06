<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            // T-23: immediate | briefing | digest | off. Null on the '*' rows
            // a client unsubscribe writes — those carry no event to have a
            // mode for and keep answering through `enabled`.
            $table->string('mode')->nullable();
        });

        // The three-way choice as it stood, read per person and event rather
        // than per row: mail on was sofort, mail off with an in-app row was
        // the summary, both off was aus. Both rows of a pair get the same
        // answer, which is what makes it a choice again.
        $rows = DB::table('notification_preferences')->where('notification_class', '!=', '*')->get();

        foreach ($rows->groupBy(fn ($row) => implode('|', [$row->notifiable_type, $row->notifiable_id, $row->notification_class])) as $pair) {
            $on = fn (string $channel) => $pair->firstWhere('channel', $channel)?->enabled ?? true;

            $mode = match (true) {
                $on('mail') => 'immediate',
                $on('in_app') => 'digest',
                default => 'off',
            };

            DB::table('notification_preferences')
                ->whereIn('id', $pair->pluck('id'))
                ->update(['mode' => $mode]);
        }
    }

    public function down(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->dropColumn('mode');
        });
    }
};
