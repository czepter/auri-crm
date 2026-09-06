<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // T-20: how it ended, where lost_at only said "not booked".
            // lost_at keeps its name and is still stamped for all three —
            // renaming it would drag through BookingFunnel,
            // CheckDateAvailability and the board queries without answering
            // a question that was open before.
            $table->string('outcome')->nullable()->after('lost_reason');
            $table->unsignedSmallInteger('stalled_reminders_sent')->default(0);
            $table->timestampTz('stalled_reminded_at')->nullable();
            $table->index(['tenant_id', 'outcome']);
        });

        DB::table('leads')->whereNotNull('lost_at')->update(['outcome' => 'lost']);
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'outcome']);
            $table->dropColumn(['outcome', 'stalled_reminders_sent', 'stalled_reminded_at']);
        });
    }
};
