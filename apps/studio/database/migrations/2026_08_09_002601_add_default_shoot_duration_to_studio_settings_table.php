<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            // Retires ShootCalendar's hardcoded addHours(2) — minutes, not
            // hours, since a shorter session (T-10) needs finer granularity
            // than that constant offered. 120 is the exact value it
            // replaces, so every existing studio keeps today's behaviour
            // until someone changes it.
            $table->unsignedInteger('default_shoot_duration_minutes')->default(120);
        });

        DB::statement('alter table studio_settings add constraint studio_settings_default_shoot_duration_check check (default_shoot_duration_minutes > 0)');
    }

    public function down(): void
    {
        Schema::table('studio_settings', function (Blueprint $table) {
            $table->dropColumn('default_shoot_duration_minutes');
        });
    }
};
